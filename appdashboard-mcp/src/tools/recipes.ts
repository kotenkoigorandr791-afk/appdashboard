import {
  apiBinaryPostDirect,
  apiFormPost,
  apiGet,
  apiJsonPostDirect,
  apiSsePostDirect,
  apiUpload,
  textResult,
} from "../api.js";
import type { ToolDef } from "./types.js";

const RECIPES = "/public-api/recipes";
const AI = "/apps/recipes/recipes_ai.php";
const TRANSLATE = "/apps/recipes/translate_api.php";
const FETCH_IMAGE = "/apps/recipes/fetch_image_url.php";
const REMOVE_BG = "/apps/recipes/remove_background.php";

const CATEGORIES = [
  "vegetables",
  "fruits",
  "meat",
  "fish",
  "dairy",
  "grains",
  "spices",
  "oils",
  "sauces",
  "nuts",
  "legumes",
  "bread",
  "pasta",
  "rice",
  "flour",
  "sugar",
  "salt",
  "vinegar",
  "condiments",
  "herbs",
] as const;

const UNITS = ["grams", "pieces", "teaspoon", "tablespoon"] as const;
const LANGS = ["en", "uk", "ru", "be"] as const;

const langProp = { type: "string", enum: [...LANGS], description: "Language: en|uk|ru|be" };
const categoryProp = { type: "string", enum: [...CATEGORIES] };
const i18nName = {
  type: "object",
  properties: { en: { type: "string" }, uk: { type: "string" }, ru: { type: "string" }, be: { type: "string" } },
};

function str(v: unknown): string | undefined {
  return v == null || v === "" ? undefined : String(v);
}

function recipeFields(args: Record<string, unknown>, action: "add_recipe" | "edit_recipe") {
  const recipesData = (args.recipes_data ?? args.recipe) as Record<string, unknown> | undefined;
  const lang = str(args.lang) || "en";
  let titleObj: Record<string, string> | null = null;
  let titleStr = str(args.title) || "";
  let steps: unknown = args.steps;

  if (recipesData && typeof recipesData === "object") {
    const t = recipesData.title;
    if (t && typeof t === "object" && !Array.isArray(t)) {
      titleObj = t as Record<string, string>;
      titleStr = titleObj[lang] || titleObj.en || Object.values(titleObj)[0] || "";
    } else if (typeof t === "string") {
      titleStr = t;
    }
    if (recipesData.steps != null) steps = recipesData.steps;
  }

  const stepsJson = typeof steps === "string" ? steps : JSON.stringify(steps ?? []);
  const pretranslated = titleObj != null && LANGS.every((l) => titleObj![l] != null && titleObj![l] !== "");
  const fields: Record<string, string> = {
    action: pretranslated ? `${action}_pretranslated` : action,
    title: titleStr,
    steps: stepsJson,
    lang,
  };
  if (pretranslated) fields.translated_title = JSON.stringify(titleObj);
  if (action === "edit_recipe") {
    const id = str(args.recipe_id) || str(recipesData?.id);
    if (id && id !== "new") fields.recipe_id = id;
  } else if (str(args.recipe_id) && str(args.recipe_id) !== "new") {
    fields.recipe_id = String(args.recipe_id);
  }
  return fields;
}

async function postRecipe(args: Record<string, unknown>, action: "add_recipe" | "edit_recipe") {
  const fields = recipeFields(args, action);
  const imagePath = str(args.image_path) || str(args.image);
  if (imagePath && !imagePath.startsWith("http") && imagePath.includes("/")) {
    return apiUpload(RECIPES, fields, [{ field: "image", filePath: imagePath }]);
  }
  return apiFormPost(RECIPES, fields);
}

async function postProduct(
  args: Record<string, unknown>,
  productId: string,
): Promise<unknown> {
  const lang = str(args.lang) || "en";
  const fields: Record<string, string> = {
    action: "edit_product_name",
    product_id: productId,
    name_en: str(args.name_en) || "",
    name_uk: str(args.name_uk) || "",
    name_ru: str(args.name_ru) || "",
    name_be: str(args.name_be) || "",
    category: str(args.category) || "",
    lang,
  };
  const name = args.name;
  if (typeof name === "object" && name && !Array.isArray(name)) {
    const n = name as Record<string, string>;
    fields.name_en = fields.name_en || n.en || "";
    fields.name_uk = fields.name_uk || n.uk || "";
    fields.name_ru = fields.name_ru || n.ru || "";
    fields.name_be = fields.name_be || n.be || "";
  } else if (typeof name === "string" && name) {
    if (!fields.name_en && !fields.name_uk && !fields.name_ru && !fields.name_be) {
      fields[`name_${lang}`] = name;
    }
  }
  const imagePath = str(args.image_path) || (str(args.image)?.includes("/") ? str(args.image) : undefined);
  if (imagePath) {
    return apiUpload(RECIPES, fields, [{ field: "image", filePath: imagePath }]);
  }
  return apiFormPost(RECIPES, fields);
}

async function listRecipesFull() {
  const data = await apiGet(RECIPES, { get_recipes: 1 });
  const list = Array.isArray(data) ? data : data?.recipes ?? [];
  const full = [];
  for (const r of list) {
    const id = r?.id;
    if (!id) {
      full.push(r);
      continue;
    }
    try {
      const one = await apiGet(RECIPES, { get_recipe_full: 1, recipe_id: id });
      full.push(one?.data ?? one?.recipe ?? one ?? r);
    } catch {
      full.push(r);
    }
  }
  return { recipes: full };
}

export const recipesTools: ToolDef[] = [
  {
    name: "recipes_list",
    description: "List all recipes with full multilingual titles, images, and steps.",
    minRole: 0,
    inputSchema: { type: "object", properties: {} },
    handler: async () => textResult(await listRecipesFull()),
  },
  {
    name: "recipes_products",
    description: "List all products (ingredients) with multilingual names, images, and categories.",
    minRole: 0,
    inputSchema: { type: "object", properties: {} },
    handler: async () => textResult(await apiGet(RECIPES, { get_products: 1 })),
  },
  {
    name: "recipes_add_product",
    description:
      "Create a product. Pass name_* (or name object) and optional category/image_path. Missing languages are auto-translated from lang.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        name: i18nName,
        name_en: { type: "string" },
        name_uk: { type: "string" },
        name_ru: { type: "string" },
        name_be: { type: "string" },
        category: categoryProp,
        lang: langProp,
        image_path: { type: "string", description: "Local file path for multipart upload" },
      },
    },
    handler: async (args) => textResult(await postProduct(args, "new")),
  },
  {
    name: "recipes_update_product",
    description: "Update an existing product name/category/image.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        product_id: { type: "string" },
        name: i18nName,
        name_en: { type: "string" },
        name_uk: { type: "string" },
        name_ru: { type: "string" },
        name_be: { type: "string" },
        category: categoryProp,
        lang: langProp,
        image_path: { type: "string" },
        clear_image: { type: "boolean" },
      },
      required: ["product_id"],
    },
    handler: async (args) => {
      if (args.clear_image === true) {
        const lang = str(args.lang) || "en";
        const fields: Record<string, string> = {
          action: "edit_product_name",
          product_id: String(args.product_id),
          name_en: str(args.name_en) || "",
          name_uk: str(args.name_uk) || "",
          name_ru: str(args.name_ru) || "",
          name_be: str(args.name_be) || "",
          category: str(args.category) || "",
          lang,
          clear_product_image: "1",
        };
        const name = args.name;
        if (typeof name === "object" && name && !Array.isArray(name)) {
          const n = name as Record<string, string>;
          fields.name_en = fields.name_en || n.en || "";
          fields.name_uk = fields.name_uk || n.uk || "";
          fields.name_ru = fields.name_ru || n.ru || "";
          fields.name_be = fields.name_be || n.be || "";
        }
        return textResult(await apiFormPost(RECIPES, fields));
      }
      return textResult(await postProduct(args, String(args.product_id)));
    },
  },
  {
    name: "recipes_delete_product",
    description: "Delete a product by id.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { product_id: { type: "string" } },
      required: ["product_id"],
    },
    handler: async (args) =>
      textResult(await apiFormPost(RECIPES, { action: "delete_product", product_id: String(args.product_id) })),
  },
  {
    name: "recipes_add",
    description:
      "Create a recipe. Pass recipes_data {title, steps} (title may be i18n object) or title+steps. Product steps use content=product_id; instruction content is text or i18n object. Units: " +
      UNITS.join(", ") +
      ".",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        recipes_data: { type: "object", description: "Full recipe object with title and steps" },
        title: { description: "Title string or i18n object" },
        steps: { type: "array" },
        lang: langProp,
        image_path: { type: "string" },
      },
    },
    handler: async (args) => textResult(await postRecipe(args, "add_recipe")),
  },
  {
    name: "recipes_update",
    description: "Update an existing recipe (same payload as recipes_add plus recipe_id).",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        recipe_id: { type: "string" },
        recipes_data: { type: "object" },
        title: {},
        steps: { type: "array" },
        lang: langProp,
        image_path: { type: "string" },
      },
      required: ["recipe_id"],
    },
    handler: async (args) => textResult(await postRecipe(args, "edit_recipe")),
  },
  {
    name: "recipes_delete",
    description: "Delete a recipe by id.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { recipe_id: { type: "string" } },
      required: ["recipe_id"],
    },
    handler: async (args) =>
      textResult(await apiFormPost(RECIPES, { action: "delete_recipe", recipe_id: String(args.recipe_id) })),
  },
  {
    name: "recipes_save_state",
    description: "Save Recipes UI state (e.g. current_recipe, completed_steps).",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        state: { type: "object" },
      },
      required: ["state"],
    },
    handler: async (args) =>
      textResult(
        await apiFormPost(RECIPES, {
          action: "save_state",
          state: typeof args.state === "string" ? args.state : JSON.stringify(args.state ?? {}),
        }),
      ),
  },
  {
    name: "recipes_save_config",
    description: "Save Recipes app config (e.g. items_per_page).",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { config: { type: "object" } },
      required: ["config"],
    },
    handler: async (args) =>
      textResult(
        await apiFormPost(RECIPES, {
          action: "save_config",
          config: typeof args.config === "string" ? args.config : JSON.stringify(args.config ?? {}),
        }),
      ),
  },
  {
    name: "recipes_ai_config",
    description:
      "Get Recipes AI config (models, default_model, has_key). Requires Manager+ (role ≥ 1) on the server.",
    minRole: 0,
    inputSchema: { type: "object", properties: {} },
    handler: async () => textResult(await apiJsonPostDirect(AI, { action: "config" })),
  },
  {
    name: "recipes_ai_generate",
    description:
      "Generate a recipe via AI (SSE). Returns collected events plus final done payload. Requires Manager+ (role ≥ 1).",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        prompt: { type: "string" },
        lang: langProp,
        model: { type: "string" },
      },
      required: ["prompt"],
    },
    handler: async (args) => {
      const result = await apiSsePostDirect(AI, {
        action: "generate_stream",
        prompt: String(args.prompt),
        lang: str(args.lang) || "en",
        model: str(args.model),
      });
      return textResult({
        success: !result.error,
        done: result.done,
        error: result.error,
        events: result.events,
      });
    },
  },
  {
    name: "recipes_ai_edit",
    description:
      "Edit a recipe draft via AI (SSE). Pass instruction + draft. Requires Manager+ (role ≥ 1).",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        instruction: { type: "string" },
        draft: { type: "object" },
        lang: langProp,
        model: { type: "string" },
      },
      required: ["instruction", "draft"],
    },
    handler: async (args) => {
      const result = await apiSsePostDirect(AI, {
        action: "edit_stream",
        instruction: String(args.instruction),
        draft: args.draft,
        lang: str(args.lang) || "en",
        model: str(args.model),
      });
      return textResult({
        success: !result.error,
        done: result.done,
        error: result.error,
        events: result.events,
      });
    },
  },
  {
    name: "recipes_ai_save",
    description:
      "Save an AI recipe draft to the database (translates to all languages; downloads image URL if set). Requires Manager+ (role ≥ 1).",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        draft: { type: "object" },
        lang: langProp,
        stream: { type: "boolean", description: "If true, use save_stream SSE" },
      },
      required: ["draft"],
    },
    handler: async (args) => {
      if (args.stream === true) {
        const result = await apiSsePostDirect(AI, {
          action: "save_stream",
          draft: args.draft,
          lang: str(args.lang) || "en",
        });
        return textResult({ success: !result.error, done: result.done, error: result.error, events: result.events });
      }
      return textResult(
        await apiJsonPostDirect(AI, {
          action: "save",
          draft: args.draft,
          lang: str(args.lang) || "en",
        }),
      );
    },
  },
  {
    name: "recipes_ai_image_options",
    description:
      "Search image options for a recipe or product title. Requires Manager+ (role ≥ 1).",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        title: { type: "string" },
        image_query: { type: "string" },
        kind: { type: "string", enum: ["recipe", "product"] },
      },
      required: ["title"],
    },
    handler: async (args) =>
      textResult(
        await apiJsonPostDirect(AI, {
          action: "image_options",
          title: String(args.title),
          image_query: str(args.image_query) || String(args.title),
          kind: str(args.kind) === "product" ? "product" : "recipe",
        }),
      ),
  },
  {
    name: "recipes_ai_verify_image",
    description: "Verify/search accessible images for a title. Requires Manager+ (role ≥ 1).",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        title: { type: "string" },
        image_query: { type: "string" },
      },
      required: ["title"],
    },
    handler: async (args) =>
      textResult(
        await apiJsonPostDirect(AI, {
          action: "verify_image",
          title: String(args.title),
          image_query: str(args.image_query) || String(args.title),
        }),
      ),
  },
  {
    name: "recipes_translate",
    description: "Translate food/recipe text between en/uk/ru/be (MyMemory + food context).",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        text: { type: "string" },
        source_lang: langProp,
        target_langs: { type: "array", items: { type: "string" } },
        context: { type: "string", description: "Default: food" },
      },
      required: ["text", "source_lang"],
    },
    handler: async (args) =>
      textResult(
        await apiJsonPostDirect(TRANSLATE, {
          text: String(args.text),
          source_lang: String(args.source_lang),
          target_langs: Array.isArray(args.target_langs) ? args.target_langs : ["uk", "ru", "be"],
          context: str(args.context) || "food",
        }),
      ),
  },
  {
    name: "recipes_fetch_image",
    description:
      "Fetch an image URL (SSRF-protected, max 10MB). Returns base64 + content_type. Requires Manager+ (role ≥ 1).",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { url: { type: "string" } },
      required: ["url"],
    },
    handler: async (args) => {
      const result = await apiBinaryPostDirect(FETCH_IMAGE, { url: String(args.url) });
      if (result.json) return textResult(result.json);
      return textResult({
        success: true,
        content_type: result.content_type,
        base64: result.base64,
        bytes: Buffer.from(result.base64, "base64").length,
      });
    },
  },
  {
    name: "recipes_remove_bg",
    description: "Remove image background (rembg/PIL). Pass local image_path. Returns base64 PNG.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { image_path: { type: "string" } },
      required: ["image_path"],
    },
    handler: async (args) =>
      textResult(await apiUpload(REMOVE_BG, {}, [{ field: "image", filePath: String(args.image_path) }])),
  },
];
