(() => {
  const displays = new Map([
    ["32x8", { pcb: "red", type: "led" }],
    ["32x16", { pcb: "blue", type: "oled" }],
    ["32x32", { pcb: "black", type: "oled" }],
    ["32x64", { pcb: "black", type: "oled" }],
    ["64x32", { pcb: "black", type: "oled" }],
    ["64x48", { pcb: "black", type: "oled" }],
    ["64x128", { pcb: "black", type: "oled" }],
    ["72x40", { pcb: "black", type: "oled" }],
    ["84x48", { pcb: "black", type: "oled" }],
    ["96x16", { pcb: "black", type: "oled" }],
    ["96x32", { pcb: "black", type: "oled" }],
    ["96x40", { pcb: "black", type: "oled" }],
    ["96x96", { pcb: "black", type: "oled" }],
    ["128x32", { pcb: "blue", type: "oled" }],
    ["128x36", { pcb: "black", type: "oled" }],
    ["128x64", { pcb: "blue", type: "oled" }],
    ["128x80", { pcb: "red", type: "tft" }],
    ["128x96", { pcb: "red", type: "tft" }],
    ["128x128", { pcb: "red", type: "tft" }],
  ]);
  let modal;

  function element(parent, className, x, y, width, height, extra) {
    const item = document.createElement("span");
    item.className = className;
    Object.assign(item.style, { left: `${x}px`, top: `${y}px` }, extra);
    if (width != null) item.style.width = `${width}px`;
    if (height != null) item.style.height = `${height}px`;
    parent.append(item);
    return item;
  }

  function activeScreen(parent, className, x, y, width, height, source) {
    const screen = element(parent, className, x, y, width, height);
    const image = document.createElement("img");
    image.src = source;
    image.alt = "Selected screen";
    image.className = "pixelated";
    screen.append(image);
  }

  function buildTFT(module, width, height, source) {
    const K = 1.25,
      activeWidth = Math.round(width * K),
      activeHeight = Math.round(height * K);
    const border = 4,
      glassWidth = activeWidth + border * 2,
      glassHeight = activeHeight + border * 2;
    const side = 22,
      top = 14,
      bottom = 8,
      moduleWidth = side * 2 + glassWidth,
      moduleHeight = top + glassHeight + bottom;
    Object.assign(module.style, {
      width: `${moduleWidth}px`,
      height: `${moduleHeight}px`,
    });
    const glass = element(
      module,
      "lopaka-glass",
      side,
      top,
      glassWidth,
      glassHeight,
    );
    activeScreen(
      glass,
      "lopaka-tft-active",
      border - 1,
      border - 1,
      activeWidth + 2,
      activeHeight + 2,
      source,
    );
    const topHole = 20,
      bottomHole = moduleHeight - 20;
    [
      [side / 2, topHole],
      [side / 2, bottomHole],
      [moduleWidth - side / 2, topHole],
      [moduleWidth - side / 2, bottomHole],
    ].forEach(([x, y]) => element(module, "lopaka-hole", x - 7, y - 7));
    const pinStart = Math.round(moduleHeight / 2 - 22);
    for (let index = 0; index < 5; index++)
      element(module, "lopaka-pad", side / 2 - 4, pinStart + index * 11);
    const flexY = 34,
      flexHeight = Math.round(glassHeight * 0.3);
    element(module, "lopaka-flex", side + glassWidth, flexY, null, flexHeight);
    element(
      module,
      "lopaka-flex-in",
      side + glassWidth - 12,
      flexY + 4,
      12,
      flexHeight - 8,
    );
    const start = flexY + flexHeight + 10,
      end = bottomHole - 12;
    for (
      let index = 0;
      index < Math.max(3, Math.floor((end - start) / 4));
      index++
    )
      element(
        module,
        "lopaka-rpad",
        moduleWidth - side / 2 - 4,
        start + index * 4,
      );
    return { width: moduleWidth, height: moduleHeight };
  }

  function buildOLED(module, width, height, source, led) {
    const K = 1.6,
      activeWidth = Math.round(width * K),
      activeHeight = Math.round(height * K);
    const side = 6,
      top = 20,
      bottom = 14,
      moduleWidth = Math.max(activeWidth + side * 2, 63),
      moduleHeight = top + activeHeight + bottom;
    Object.assign(module.style, {
      width: `${moduleWidth}px`,
      height: `${moduleHeight}px`,
    });
    activeScreen(
      module,
      `lopaka-screen${led ? " lopaka-led" : ""}`,
      Math.round((moduleWidth - activeWidth) / 2),
      top,
      activeWidth,
      activeHeight,
      source,
    );
    const pinStart = Math.round((moduleWidth - 35) / 2);
    for (let index = 0; index < 4; index++)
      element(module, "lopaka-pad", pinStart + index * 9, 6);
    [
      [6, 10],
      [moduleWidth - 6, 10],
      [6, moduleHeight - 7],
      [moduleWidth - 6, moduleHeight - 7],
    ].forEach(([x, y]) =>
      element(module, "lopaka-hole lopaka-hole-sm", x - 5, y - 5),
    );
    element(
      module,
      "lopaka-oflex",
      Math.round(moduleWidth / 2 - 10),
      top + activeHeight + 2,
      20,
    );
    return { width: moduleWidth, height: moduleHeight };
  }

  function selectedCanvas() {
    return document.querySelector("canvas.screen");
  }
  function render() {
    const canvas = selectedCanvas();
    if (!canvas) return;
    const key = `${canvas.width}x${canvas.height}`;
    const display = displays.get(key) || { pcb: "black", type: "oled" };
    const host = modal.querySelector(".lopaka-module-host");
    const module = document.createElement("div");
    module.className = `lopaka-module lopaka-pcb-${display.pcb}`;
    const source = canvas.toDataURL("image/png");
    const size =
      display.type === "tft"
        ? buildTFT(module, canvas.width, canvas.height, source)
        : buildOLED(
            module,
            canvas.width,
            canvas.height,
            source,
            display.type === "led",
          );
    const scale = Math.min(3.4, 720 / size.width, 430 / size.height);
    host.replaceChildren(module);
    Object.assign(host.style, {
      width: `${size.width * scale}px`,
      height: `${size.height * scale}px`,
    });
    module.style.transform = `scale(${scale})`;
    modal.querySelector(".lopaka-preview__type").textContent =
      display.type === "led" ? "LED matrix" : display.type.toUpperCase();
    modal.querySelector(".lopaka-preview__size").textContent =
      `${canvas.width} × ${canvas.height} px`;
  }

  function openPreview() {
    render();
    modal.classList.add("is-open");
    modal.querySelector(".lopaka-preview__close").focus();
  }
  function closePreview() {
    modal.classList.remove("is-open");
  }
  function init() {
    modal = document.createElement("div");
    modal.className = "lopaka-preview";
    modal.innerHTML =
      '<div class="lopaka-preview__backdrop"></div><section class="lopaka-preview__dialog" role="dialog" aria-modal="true"><header><div><span>DISPLAY PREVIEW</span><h2>Selected screen</h2></div><button class="lopaka-preview__close" type="button" aria-label="Close">×</button></header><div class="lopaka-preview__stage"><div class="lopaka-module-host"></div></div><footer><strong class="lopaka-preview__type"></strong><span class="lopaka-preview__size"></span></footer></section>';
    document.body.append(modal);
    modal.querySelector(".lopaka-preview__backdrop").onclick = closePreview;
    modal.querySelector(".lopaka-preview__close").onclick = closePreview;
    document.addEventListener(
      "keydown",
      (event) => event.key === "Escape" && closePreview(),
    );
    const observer = new MutationObserver(addButton);
    observer.observe(document.getElementById("lopaka_app"), {
      childList: true,
      subtree: true,
    });
    addButton();
  }
  function addButton() {
    if (document.querySelector(".lopaka-preview-trigger")) return;
    const toolbar =
      document.querySelector(".fui-editor__top .fui-toolbar-row") ||
      document.querySelector(".fui-editor__top");
    if (!toolbar) return;
    const button = document.createElement("button");
    button.className = "btn btn-sm lopaka-preview-trigger";
    button.type = "button";
    button.innerHTML =
      '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.4-6 9.5-6 9.5 6 9.5 6-3.4 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.7"/></svg> Preview';
    button.onclick = openPreview;
    toolbar.append(button);
  }
  document.readyState === "loading"
    ? document.addEventListener("DOMContentLoaded", init)
    : init();
})();
