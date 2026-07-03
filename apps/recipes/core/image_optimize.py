#!/usr/bin/env python3
import os
import sys
from PIL import Image, ImageOps


def main() -> int:
    if len(sys.argv) != 4:
        return 2
    src_path, dst_base, max_side_raw = sys.argv[1], sys.argv[2], sys.argv[3]
    try:
        max_side = max(1, int(max_side_raw))
    except ValueError:
        max_side = 1600

    with Image.open(src_path) as img:
        img = ImageOps.exif_transpose(img)
        img.thumbnail((max_side, max_side), Image.Resampling.LANCZOS)
        dst_dir = os.path.dirname(dst_base)
        os.makedirs(dst_dir, exist_ok=True)

        webp_path = dst_base + ".webp"
        try:
            save_img = img.convert("RGBA") if img.mode in ("RGBA", "LA", "P") else img.convert("RGB")
            save_img.save(webp_path, "WEBP", quality=78, method=6)
            print(os.path.basename(webp_path))
            return 0
        except Exception:
            jpg_path = dst_base + ".jpg"
            bg = Image.new("RGB", img.size, (255, 255, 255))
            if img.mode in ("RGBA", "LA"):
                bg.paste(img.convert("RGBA"), mask=img.convert("RGBA").getchannel("A"))
            else:
                bg.paste(img.convert("RGB"))
            bg.save(jpg_path, "JPEG", quality=82, optimize=True, progressive=True)
            print(os.path.basename(jpg_path))
            return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except Exception:
        raise SystemExit(1)
