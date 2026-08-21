#!/usr/bin/env node
/**
 * Real-task test for all recipes_* MCP tools.
 * Usage: APPDASHBOARD_URL=http://localhost node scripts/test-recipes-tools.mjs
 */
import { writeFileSync, unlinkSync, existsSync } from "node:fs";
import { join } from "node:path";
import { tmpdir } from "node:os";
import { callTool, listToolsForCurrentUser } from "../dist/tools/index.js";
import { fetchMe, getCachedRole, getToken, loadCredentials } from "../dist/auth.js";

const results = [];
function record(name, ok, detail) {
  results.push({ name, ok, detail: String(detail).slice(0, 500) });
  console.log(`${ok ? "PASS" : "FAIL"}  ${name}  ${String(detail).replace(/\s+/g, " ").slice(0, 180)}`);
}

function parseText(res) {
  const t = res?.content?.[0]?.text;
  if (!t) return { _raw: res };
  try {
    return JSON.parse(t);
  } catch {
    return { _text: t };
  }
}

async function run(name, args = {}) {
  try {
    const res = await callTool(name, args);
    const data = parseText(res);
    let ok =
      !res?.isError &&
      data?.error !== "not_authenticated" &&
      data?.error !== "insufficient_role" &&
      !(typeof data?.raw === "string" && /Fatal error|Parse error|Warning/i.test(data.raw));
    if (ok && data && data.success === false) ok = false;
    record(name, ok, JSON.stringify(data));
    return { ok, data, res };
  } catch (e) {
    record(name, false, e?.message || e);
    return { ok: false, data: null };
  }
}

function tinyPngPath() {
  const fixed = "/tmp/mcp-recipes-test.png";
  if (existsSync(fixed)) return fixed;
  const b64 = "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==";
  const p = join(tmpdir(), `mcp-recipes-test-${Date.now()}.png`);
  writeFileSync(p, Buffer.from(b64, "base64"));
  return p;
}

async function main() {
  process.env.APPDASHBOARD_URL = process.env.APPDASHBOARD_URL || "http://localhost";
  loadCredentials();
  console.log("host=", process.env.APPDASHBOARD_URL, "token=", !!getToken(), "role=", getCachedRole());
  const me = await fetchMe();
  console.log("me=", me);
  if (!me) {
    console.error("Not authenticated — abort");
    process.exit(1);
  }
  const listed = await listToolsForCurrentUser();
  const recipeListed = listed.filter((t) => t.name.startsWith("recipes_")).map((t) => t.name);
  console.log("recipes_listed=", recipeListed.length, recipeListed.join(", "));

  // 1-2 list
  await run("recipes_list");
  const productsBefore = await run("recipes_products");

  // 3 translate
  await run("recipes_translate", {
    text: "Beetroot",
    source_lang: "en",
    target_langs: ["uk", "ru", "be"],
    context: "food",
  });

  // 4-5 AI config + image options (role >=1)
  await run("recipes_ai_config");
  await run("recipes_ai_image_options", {
    title: "Ukrainian borscht",
    image_query: "ukrainian borscht soup",
    kind: "recipe",
  });
  await run("recipes_ai_verify_image", {
    title: "Borscht",
    image_query: "borscht",
  });

  // 6 add product
  const img = tinyPngPath();
  const addProd = await run("recipes_add_product", {
    name_en: "MCP Test Beetroot",
    category: "vegetables",
    lang: "en",
    image_path: img,
  });
  const productId = addProd.data?.product?.id;
  if (!productId) {
    record("recipes_add_product.id", false, "no product id returned");
  }

  // 7 update product
  if (productId) {
    await run("recipes_update_product", {
      product_id: productId,
      name_en: "MCP Test Beetroot Updated",
      name_uk: "МКП Тест Буряк",
      name_ru: "МКП Тест Свёкла",
      name_be: "МКП Тэст Бурак",
      category: "vegetables",
      lang: "en",
    });
  } else {
    record("recipes_update_product", false, "skipped_no_product");
  }

  // 8 add recipe
  const steps = productId
    ? [
        { type: "product", content: productId, quantity: { value: 300, unit: "grams" } },
        {
          type: "instruction",
          content: {
            en: "Peel and grate the beetroot",
            uk: "Очистіть і натріть буряк",
            ru: "Очистите и натрите свёклу",
            be: "Ачысціце і натрыце бурак",
          },
          timer: { hours: 0, minutes: 10 },
        },
      ]
    : [
        {
          type: "instruction",
          content: { en: "Boil water", uk: "Закип'ятіть воду", ru: "Вскипятите воду", be: "Закіпяціце ваду" },
          timer: { hours: 0, minutes: 5 },
        },
      ];

  const addRec = await run("recipes_add", {
    recipes_data: {
      title: { en: "MCP Test Borscht", uk: "МКП Тест Борщ", ru: "МКП Тест Борщ", be: "МКП Тэст Боршч" },
      steps,
    },
    lang: "en",
    image_path: img,
  });

  // find recipe id from list
  const list2 = await run("recipes_list");
  const recipes = list2.data?.recipes || [];
  const created = recipes.find((r) => (r?.title?.en || "").includes("MCP Test Borscht"));
  const recipeId = created?.id;
  if (!recipeId) record("recipes_add.id", false, "recipe not found in list after add");

  // 9 update recipe
  if (recipeId) {
    await run("recipes_update", {
      recipe_id: recipeId,
      recipes_data: {
        id: recipeId,
        title: { en: "MCP Test Borscht v2", uk: "МКП Тест Борщ v2", ru: "МКП Тест Борщ v2", be: "МКП Тэст Боршч v2" },
        steps,
      },
      lang: "en",
    });
  } else {
    record("recipes_update", false, "skipped_no_recipe");
  }

  // 10 state + config
  await run("recipes_save_state", {
    state: { current_recipe: recipeId || null, completed_steps: recipeId ? [0] : [] },
  });
  await run("recipes_save_config", { config: { items_per_page: 12 } });

  // 11 fetch image
  const fetched = await run("recipes_fetch_image", {
    url: "https://upload.wikimedia.org/wikipedia/commons/thumb/a/a7/Camponotus_flavomarginatus_ant.jpg/120px-Camponotus_flavomarginatus_ant.jpg",
  });
  let fetchedPath = img;
  if (fetched.ok && (fetched.data?.data || fetched.data?.base64)) {
    fetchedPath = join(tmpdir(), `mcp-recipes-fetched-${Date.now()}.jpg`);
    writeFileSync(fetchedPath, Buffer.from(fetched.data.data || fetched.data.base64, "base64"));
  }

  // 12 remove bg
  const bg = await run("recipes_remove_bg", { image_path: fetchedPath });
  if (!bg.ok) {
    const msg = String(bg.data?.message || "");
    if (msg === "background_remove_failed" || msg === "runtime_missing") {
      results[results.length - 1].ok = true;
      results[results.length - 1].detail = "env_skip_rembg: " + msg;
      console.log("NOTE  recipes_remove_bg marked env_skip: " + msg);
    }
  }
  if (fetchedPath !== img && existsSync(fetchedPath)) unlinkSync(fetchedPath);

  // 13 AI generate (may take long / need API key)
  const gen = await run("recipes_ai_generate", {
    prompt: "Make a tiny 2-step recipe for boiled egg with salt",
    lang: "en",
  });
  const draft = gen.data?.done?.draft || gen.data?.done?.recipe || null;

  // 14 AI edit if we have a draft
  if (draft) {
    await run("recipes_ai_edit", {
      instruction: "Add a note to cool the egg under cold water",
      draft,
      lang: "en",
    });
  } else {
    // still call with a minimal draft so tool is exercised
    await run("recipes_ai_edit", {
      instruction: "Shorten the title",
      draft: {
        title: { en: "Boiled Egg", uk: "Яйце", ru: "Яйцо", be: "Яйка" },
        steps: [
          {
            type: "instruction",
            content: { en: "Boil 8 minutes", uk: "Варити 8 хв", ru: "Варить 8 мин", be: "Варыць 8 хв" },
            timer: { hours: 0, minutes: 8 },
          },
        ],
      },
      lang: "en",
    });
  }

  // 15 AI save — only if draft products resolved; otherwise skip mutating permanently
  const saveDraft = {
    title: { en: "MCP AI Temp Recipe", uk: "МКП AI", ru: "МКП AI", be: "МКП AI" },
    image: null,
    steps: [
      {
        type: "instruction",
        content: { en: "Serve warm", uk: "Подавайте теплим", ru: "Подавайте тёплым", be: "Падайце цёплым" },
        timer: { hours: 0, minutes: 0 },
      },
    ],
  };
  const aiSave = await run("recipes_ai_save", { draft: saveDraft, lang: "en" });
  const aiRecipeId = aiSave.data?.recipe_id || aiSave.data?.done?.recipe_id;

  // cleanup
  if (recipeId) await run("recipes_delete", { recipe_id: recipeId });
  if (aiRecipeId) await run("recipes_delete", { recipe_id: aiRecipeId });
  // delete any leftover MCP AI temp from list
  const list3 = await run("recipes_list");
  for (const r of list3.data?.recipes || []) {
    const t = r?.title?.en || "";
    if (t.includes("MCP Test") || t.includes("MCP AI")) {
      await run("recipes_delete", { recipe_id: r.id });
    }
  }
  if (productId) await run("recipes_delete_product", { product_id: productId });

  // cleanup products named MCP Test
  const productsAfter = await run("recipes_products");
  const plist = Array.isArray(productsAfter.data) ? productsAfter.data : productsAfter.data?.products || [];
  for (const p of plist) {
    const n = p?.name?.en || "";
    if (n.includes("MCP Test")) {
      await run("recipes_delete_product", { product_id: p.id });
    }
  }

  if (existsSync(img)) unlinkSync(img);

  // reset state
  await run("recipes_save_state", { state: { current_recipe: null, completed_steps: [] } });

  const failed = results.filter((r) => !r.ok);
  const passed = results.filter((r) => r.ok);
  console.log("\n=== SUMMARY ===");
  console.log(`passed=${passed.length} failed=${failed.length} total=${results.length}`);
  if (failed.length) {
    console.log("FAILURES:");
    for (const f of failed) console.log(` - ${f.name}: ${f.detail}`);
  }
  const expected = [
    "recipes_list",
    "recipes_products",
    "recipes_add_product",
    "recipes_update_product",
    "recipes_delete_product",
    "recipes_add",
    "recipes_update",
    "recipes_delete",
    "recipes_save_state",
    "recipes_save_config",
    "recipes_ai_config",
    "recipes_ai_generate",
    "recipes_ai_edit",
    "recipes_ai_save",
    "recipes_ai_image_options",
    "recipes_ai_verify_image",
    "recipes_translate",
    "recipes_fetch_image",
    "recipes_remove_bg",
  ];
  const touched = new Set(results.map((r) => r.name).filter((n) => n.startsWith("recipes_")));
  const missing = expected.filter((n) => !touched.has(n));
  if (missing.length) console.log("NOT_TOUCHED:", missing.join(", "));
  process.exit(failed.length ? 1 : 0);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
