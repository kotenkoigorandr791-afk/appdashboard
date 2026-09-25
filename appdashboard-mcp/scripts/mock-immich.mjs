#!/usr/bin/env node
/**
 * Minimal mock Immich API for testing appdashboard-mcp immich tools.
 * Usage: node scripts/mock-immich.mjs [port]  (default 4321)
 */
import http from "node:http";
import { randomUUID } from "node:crypto";

const port = Number(process.argv[2] || 4321);
const API_KEY = "mock-immich-key";
const ASSET_ID = "asset-1";
// 1x1 red PNG
const PNG_BASE64 =
  "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==";
const PNG_BUF = Buffer.from(PNG_BASE64, "base64");

const assets = [
  {
    id: ASSET_ID,
    deviceAssetId: "da-1",
    ownerId: "user-1",
    deviceId: "dev-1",
    type: "IMAGE",
    originalPath: "/photos/red.png",
    originalFileName: "red.png",
    resized: true,
    thumbhash: null,
    fileCreatedAt: new Date().toISOString(),
    fileModifiedAt: new Date().toISOString(),
    localDateTime: new Date().toISOString(),
    updatedAt: new Date().toISOString(),
    isFavorite: false,
    isArchived: false,
    isTrashed: false,
    duration: "0:00:00.000000",
    exifInfo: { city: "Kyiv", country: "Ukraine", description: "a red pixel" },
  },
];

const server = http.createServer((req, res) => {
  const auth = req.headers["x-api-key"];
  if (auth !== API_KEY) {
    res.writeHead(401, { "Content-Type": "application/json" });
    res.end(JSON.stringify({ message: "invalid api key" }));
    return;
  }
  const url = new URL(req.url, `http://localhost:${port}`);
  const p = url.pathname;
  const sendJson = (code, body) => {
    res.writeHead(code, { "Content-Type": "application/json" });
    res.end(JSON.stringify(body));
  };

  if (p === "/api/albums" && req.method === "GET") {
    sendJson(200, [{ id: "album-1", albumName: "Test Album", assetCount: 1, shared: false }]);
    return;
  }
  if (p === "/api/search/metadata" && req.method === "POST") {
    let body = "";
    req.on("data", (c) => (body += c));
    req.on("end", () => {
      sendJson(200, { assets: { items: assets, nextPage: null }, total: assets.length });
    });
    return;
  }
  if (p === "/api/search/smart" && req.method === "POST") {
    sendJson(200, { assets: { items: assets, nextPage: null } });
    return;
  }
  if (p === "/api/search/explore" && req.method === "GET") {
    sendJson(200, [{ fieldName: "city", value: "Kyvi", data: { assetId: ASSET_ID } }]);
    return;
  }
  if (p === "/api/assets/statistics" && req.method === "GET") {
    sendJson(200, { images: 1, videos: 0, total: 1 });
    return;
  }
  if (p === "/api/search/random" && req.method === "POST") {
    sendJson(200, assets);
    return;
  }
  if (p === `/api/assets/${ASSET_ID}` && req.method === "GET") {
    sendJson(200, assets[0]);
    return;
  }
  if ((p === `/api/assets/${ASSET_ID}/thumbnail` || p === `/api/assets/${ASSET_ID}/preview` || p === `/api/assets/${ASSET_ID}/original`) && req.method === "GET") {
    res.writeHead(200, {
      "Content-Type": "image/png",
      "Content-Length": PNG_BUF.length,
      "Content-Disposition": `inline; filename="red.png"`,
    });
    res.end(PNG_BUF);
    return;
  }
  sendJson(404, { message: `not found: ${p}` });
});

server.listen(port, "127.0.0.1", () => {
  console.log(`mock-immich listening on http://127.0.0.1:${port} (api key: ${API_KEY}, asset: ${ASSET_ID})`);
});
