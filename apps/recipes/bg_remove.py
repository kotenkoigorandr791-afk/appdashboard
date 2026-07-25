#!/usr/bin/env python3
"""Background removal script for recipe images.

Supports rembg (AI-based) with a numpy/scipy fallback.
NUMBA_DISABLE_CACHING is set to avoid numba cache-locator errors
in restricted filesystem environments (e.g. read-only /usr).
"""
import os

# Disable numba JIT caching to avoid "no locator available" errors
# when pymatting (used by rembg) compiles its kd-tree kernel.
os.environ.setdefault("NUMBA_DISABLE_CACHING", "1")

import json
import sys
from collections import deque
from pathlib import Path
from typing import Optional

import numpy as np
from PIL import Image, ImageFilter, ImageOps
try:
    from scipy import ndimage
except ModuleNotFoundError:
    ndimage = None
try:
    from rembg import new_session, remove as rembg_remove
except Exception:
    new_session = None
    rembg_remove = None

MAX_PROCESS_DIMENSION = 1400
_REMBG_SESSION = None


def rgb_to_hsv_features(rgb: np.ndarray) -> np.ndarray:
    rgb = np.clip(rgb, 0.0, 1.0)
    mx = rgb.max(axis=2)
    mn = rgb.min(axis=2)
    diff = mx - mn

    hue = np.zeros_like(mx)
    sat = np.where(mx > 0, diff / np.maximum(mx, 1e-6), 0.0)
    val = mx

    mask = diff > 1e-6
    r, g, b = rgb[:, :, 0], rgb[:, :, 1], rgb[:, :, 2]

    mask_r = mask & (mx == r)
    mask_g = mask & (mx == g)
    mask_b = mask & (mx == b)
    hue[mask_r] = ((g - b)[mask_r] / diff[mask_r]) % 6.0
    hue[mask_g] = ((b - r)[mask_g] / diff[mask_g]) + 2.0
    hue[mask_b] = ((r - g)[mask_b] / diff[mask_b]) + 4.0
    hue = hue / 6.0

    return np.stack([hue, sat, val], axis=2)


def mahalanobis_map(features: np.ndarray, samples: np.ndarray) -> np.ndarray:
    mean = samples.mean(axis=0)
    cov = np.cov(samples, rowvar=False)
    cov = np.atleast_2d(cov) + np.eye(samples.shape[1]) * 1e-4
    inv_cov = np.linalg.pinv(cov)
    diff = features - mean
    return np.sqrt(np.einsum("...i,ij,...j->...", diff, inv_cov, diff))


def keep_main_components(mask: np.ndarray, center_bias: bool = True) -> np.ndarray:
    labeled, total = ndimage.label(mask)
    if total == 0:
        return np.zeros_like(mask, dtype=bool)

    objects = ndimage.find_objects(labeled)
    h, w = mask.shape
    cx0, cx1 = int(w * 0.2), int(w * 0.8)
    cy0, cy1 = int(h * 0.2), int(h * 0.8)
    center = np.zeros_like(mask, dtype=bool)
    center[cy0:cy1, cx0:cx1] = True

    areas = []
    for label_id, sl in enumerate(objects, start=1):
        if sl is None:
            continue
        comp = labeled[sl] == label_id
        area = int(comp.sum())
        touches_center = bool((labeled == label_id)[center].any()) if center_bias else False
        areas.append((label_id, area, touches_center))

    areas.sort(key=lambda item: (item[2], item[1]), reverse=True)
    largest = areas[0][1]
    keep = np.zeros_like(mask, dtype=bool)
    for label_id, area, touches_center in areas:
        if area < max(128, largest * 0.08):
            continue
        if touches_center or area >= largest * 0.35:
            keep |= labeled == label_id
    return keep


def border_connected_mask(similar: np.ndarray, seeds: np.ndarray) -> np.ndarray:
    h, w = similar.shape
    visited = np.zeros((h, w), dtype=bool)
    queue = deque()
    for y, x in np.argwhere(seeds & similar):
        visited[y, x] = True
        queue.append((y, x))
    while queue:
        y, x = queue.popleft()
        if y > 0 and similar[y - 1, x] and not visited[y - 1, x]:
            visited[y - 1, x] = True
            queue.append((y - 1, x))
        if y + 1 < h and similar[y + 1, x] and not visited[y + 1, x]:
            visited[y + 1, x] = True
            queue.append((y + 1, x))
        if x > 0 and similar[y, x - 1] and not visited[y, x - 1]:
            visited[y, x - 1] = True
            queue.append((y, x - 1))
        if x + 1 < w and similar[y, x + 1] and not visited[y, x + 1]:
            visited[y, x + 1] = True
            queue.append((y, x + 1))
    return visited


def keep_largest_center_components(mask: np.ndarray) -> np.ndarray:
    h, w = mask.shape
    labels = np.zeros((h, w), dtype=np.int32)
    cx0, cx1 = int(w * 0.2), int(w * 0.8)
    cy0, cy1 = int(h * 0.2), int(h * 0.8)
    center = np.zeros_like(mask, dtype=bool)
    center[cy0:cy1, cx0:cx1] = True
    components = []
    label_id = 0
    for y in range(h):
        for x in range(w):
            if not mask[y, x] or labels[y, x] != 0:
                continue
            label_id += 1
            queue = deque([(y, x)])
            labels[y, x] = label_id
            area = 0
            touches_center = False
            while queue:
                cy, cx = queue.popleft()
                area += 1
                if center[cy, cx]:
                    touches_center = True
                if cy > 0 and mask[cy - 1, cx] and labels[cy - 1, cx] == 0:
                    labels[cy - 1, cx] = label_id
                    queue.append((cy - 1, cx))
                if cy + 1 < h and mask[cy + 1, cx] and labels[cy + 1, cx] == 0:
                    labels[cy + 1, cx] = label_id
                    queue.append((cy + 1, cx))
                if cx > 0 and mask[cy, cx - 1] and labels[cy, cx - 1] == 0:
                    labels[cy, cx - 1] = label_id
                    queue.append((cy, cx - 1))
                if cx + 1 < w and mask[cy, cx + 1] and labels[cy, cx + 1] == 0:
                    labels[cy, cx + 1] = label_id
                    queue.append((cy, cx + 1))
            components.append((label_id, area, touches_center))
    if not components:
        return np.zeros_like(mask, dtype=bool)
    components.sort(key=lambda item: (item[2], item[1]), reverse=True)
    largest = components[0][1]
    keep = np.zeros_like(mask, dtype=bool)
    for cid, area, touches_center in components:
        if area < max(128, int(largest * 0.08)):
            continue
        if touches_center or area >= largest * 0.35:
            keep |= labels == cid
    return keep


def fill_holes(mask: np.ndarray) -> np.ndarray:
    h, w = mask.shape
    inv = ~mask
    reachable = np.zeros((h, w), dtype=bool)
    queue = deque()
    for x in range(w):
        if inv[0, x] and not reachable[0, x]:
            reachable[0, x] = True
            queue.append((0, x))
        if inv[h - 1, x] and not reachable[h - 1, x]:
            reachable[h - 1, x] = True
            queue.append((h - 1, x))
    for y in range(h):
        if inv[y, 0] and not reachable[y, 0]:
            reachable[y, 0] = True
            queue.append((y, 0))
        if inv[y, w - 1] and not reachable[y, w - 1]:
            reachable[y, w - 1] = True
            queue.append((y, w - 1))
    while queue:
        y, x = queue.popleft()
        if y > 0 and inv[y - 1, x] and not reachable[y - 1, x]:
            reachable[y - 1, x] = True
            queue.append((y - 1, x))
        if y + 1 < h and inv[y + 1, x] and not reachable[y + 1, x]:
            reachable[y + 1, x] = True
            queue.append((y + 1, x))
        if x > 0 and inv[y, x - 1] and not reachable[y, x - 1]:
            reachable[y, x - 1] = True
            queue.append((y, x - 1))
        if x + 1 < w and inv[y, x + 1] and not reachable[y, x + 1]:
            reachable[y, x + 1] = True
            queue.append((y, x + 1))
    holes = inv & (~reachable)
    return mask | holes


def build_alpha_mask(image: Image.Image) -> Image.Image:
    if ndimage is None:
        rgba = image.convert("RGBA")
        arr = np.asarray(rgba, dtype=np.float32)
        alpha_in = arr[:, :, 3] > 0
        if not np.any(alpha_in):
            return Image.fromarray(np.zeros(alpha_in.shape, dtype=np.uint8), mode="L")
        rgb = arr[:, :, :3] / 255.0
        h, w = alpha_in.shape
        border = max(6, min(h, w) // 18)
        border_mask = np.zeros((h, w), dtype=bool)
        border_mask[:border, :] = True
        border_mask[-border:, :] = True
        border_mask[:, :border] = True
        border_mask[:, -border:] = True
        border_mask &= alpha_in
        samples = rgb[border_mask]
        if samples.size == 0:
            alpha = (alpha_in.astype(np.uint8) * 255)
        else:
            bg_mean = samples.mean(axis=0)
            bg_std = np.clip(samples.std(axis=0), 0.03, 1.0)
            color_score = np.sqrt(np.sum(((rgb - bg_mean) / bg_std) ** 2, axis=2))
            similar = color_score <= 2.9
            bg_connected = border_connected_mask(similar & alpha_in, border_mask)
            fg = alpha_in & (~bg_connected)
            fg_ratio = float(fg.sum()) / float(max(alpha_in.sum(), 1))
            if fg_ratio < 0.08:
                similar = color_score <= 2.5
                bg_connected = border_connected_mask(similar & alpha_in, border_mask)
                fg = alpha_in & (~bg_connected)
                fg_ratio = float(fg.sum()) / float(max(alpha_in.sum(), 1))
            if fg_ratio > 0.94:
                similar = color_score <= 3.3
                bg_connected = border_connected_mask(similar & alpha_in, border_mask)
                fg = alpha_in & (~bg_connected)
            fg = keep_largest_center_components(fg)
            fg = fill_holes(fg)
            alpha = (fg.astype(np.uint8) * 255)
        mask_img = Image.fromarray(alpha, mode="L")
        return mask_img.filter(ImageFilter.GaussianBlur(radius=0.7))
    rgba = image.convert("RGBA")
    arr = np.asarray(rgba, dtype=np.float32)
    rgb = arr[:, :, :3] / 255.0
    alpha_in = arr[:, :, 3] > 0
    h, w = alpha_in.shape

    if not np.any(alpha_in):
        return Image.fromarray(np.zeros((h, w), dtype=np.uint8), mode="L")

    hsv = rgb_to_hsv_features(rgb)
    yy, xx = np.mgrid[0:h, 0:w]
    y_norm = (yy / max(h - 1, 1)).astype(np.float32)
    x_norm = (xx / max(w - 1, 1)).astype(np.float32)

    features = np.dstack([
        rgb[:, :, 0],
        rgb[:, :, 1],
        rgb[:, :, 2],
        hsv[:, :, 1],
        hsv[:, :, 2],
    ])

    border = max(6, min(h, w) // 18)
    border_mask = np.zeros((h, w), dtype=bool)
    border_mask[:border, :] = True
    border_mask[-border:, :] = True
    border_mask[:, :border] = True
    border_mask[:, -border:] = True
    border_mask &= alpha_in

    border_samples = features[border_mask]
    bg_dist = mahalanobis_map(features, border_samples)
    border_bg_dist = bg_dist[border_mask]

    bg_threshold = max(1.9, float(np.percentile(border_bg_dist, 84)) + 0.15)
    maybe_bg = bg_dist <= bg_threshold
    sure_bg = ndimage.binary_propagation(border_mask & maybe_bg, mask=maybe_bg)

    center_mask = (
        (x_norm >= 0.22) & (x_norm <= 0.78) &
        (y_norm >= 0.14) & (y_norm <= 0.86) &
        alpha_in
    )
    saliency = bg_dist + hsv[:, :, 1] * 1.6 + (0.5 - np.abs(x_norm - 0.5)) * 0.55
    center_scores = saliency[center_mask]
    fg_seed_threshold = float(np.percentile(center_scores, 72)) if center_scores.size else float(np.percentile(saliency[alpha_in], 75))
    sure_fg = center_mask & (saliency >= fg_seed_threshold) & (~sure_bg)
    sure_fg = ndimage.binary_opening(sure_fg, structure=np.ones((3, 3), dtype=bool))
    sure_fg = ndimage.binary_closing(sure_fg, structure=np.ones((5, 5), dtype=bool))
    sure_fg = keep_main_components(sure_fg)

    if not np.any(sure_fg):
        fallback = center_mask & (~sure_bg)
        sure_fg = keep_main_components(fallback)

    fg_samples = features[sure_fg]
    if fg_samples.shape[0] < 32:
        fg_samples = features[center_mask & (~sure_bg)]
    fg_dist = mahalanobis_map(features, fg_samples)

    class_score = fg_dist - bg_dist
    foreground = (class_score < -0.28) & (~sure_bg) & alpha_in
    foreground |= sure_fg
    foreground = ndimage.binary_closing(foreground, structure=np.ones((5, 5), dtype=bool))
    foreground = ndimage.binary_fill_holes(foreground)
    foreground = ndimage.binary_opening(foreground, structure=np.ones((3, 3), dtype=bool))
    cleanup_bg = ndimage.binary_propagation(border_mask & maybe_bg, mask=maybe_bg)
    foreground &= ~cleanup_bg
    foreground = ndimage.binary_fill_holes(foreground)
    foreground = keep_main_components(foreground)

    distance_in = ndimage.distance_transform_edt(foreground)
    distance_out = ndimage.distance_transform_edt(~foreground)
    feather = np.clip((distance_in - distance_out + 2.0) / 4.0, 0.0, 1.0)

    alpha = (feather * 255.0).astype(np.uint8)
    alpha = np.minimum(alpha, (arr[:, :, 3]).astype(np.uint8))

    mask_img = Image.fromarray(alpha, mode="L")
    mask_img = mask_img.filter(ImageFilter.GaussianBlur(radius=0.55))
    return mask_img


def downscale_for_processing(image: Image.Image) -> tuple[Image.Image, tuple[int, int]]:
    original_size = image.size
    max_dim = max(original_size)
    if max_dim <= MAX_PROCESS_DIMENSION:
        return image, original_size

    scale = MAX_PROCESS_DIMENSION / float(max_dim)
    resized = image.resize(
        (max(1, int(round(image.width * scale))), max(1, int(round(image.height * scale)))),
        Image.Resampling.LANCZOS,
    )
    return resized, original_size


def remove_background(input_path: Path, output_path: Path) -> dict:
    image = ImageOps.exif_transpose(Image.open(input_path)).convert("RGBA")
    output = remove_background_with_rembg(image)
    if output is None:
        working_image, original_size = downscale_for_processing(image)
        alpha_mask = build_alpha_mask(working_image)
        if working_image.size != original_size:
            alpha_mask = alpha_mask.resize(original_size, Image.Resampling.LANCZOS)
        output = image.copy()
        output.putalpha(alpha_mask)
    output.save(output_path, format="PNG", optimize=True)

    alpha = np.asarray(output.getchannel("A"))
    stats = {
        "transparent_pixels": int((alpha < 16).sum()),
        "opaque_pixels": int((alpha > 220).sum()),
        "width": output.width,
        "height": output.height,
        "processed_width": output.width,
        "processed_height": output.height,
    }
    return stats


def remove_background_with_rembg(image: Image.Image) -> Optional[Image.Image]:
    global _REMBG_SESSION
    if rembg_remove is None or new_session is None:
        return None
    try:
        if _REMBG_SESSION is None:
            _REMBG_SESSION = new_session("u2net")
        result = rembg_remove(
            image,
            session=_REMBG_SESSION,
            alpha_matting=True,
            alpha_matting_foreground_threshold=240,
            alpha_matting_background_threshold=10,
            alpha_matting_erode_size=12,
            post_process_mask=True,
        )
        out = result.convert("RGBA")
        alpha = np.asarray(out.getchannel("A"), dtype=np.uint8)
        ratio = float((alpha > 10).sum()) / float(max(alpha.size, 1))
        if ratio < 0.02 or ratio > 0.98:
            return None
        out.putalpha(Image.fromarray(alpha, mode="L").filter(ImageFilter.GaussianBlur(radius=0.4)))
        return out
    except Exception:
        return None


def main() -> int:
    if len(sys.argv) != 3:
        print("Usage: bg_remove.py <input> <output>", file=sys.stderr)
        return 1

    input_path = Path(sys.argv[1])
    output_path = Path(sys.argv[2])
    output_path.parent.mkdir(parents=True, exist_ok=True)
    stats = remove_background(input_path, output_path)
    print(json.dumps({"success": True, "stats": stats}))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
