import { apiFormPost, apiGet, apiUpload, textResult } from "../api.js";
import type { ToolDef } from "./types.js";

const RECIPES = "/public-api/recipes";

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
        steps: { type: "array", items: { type: "object" } },
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
        steps: { type: "array", items: { type: "object" } },
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
];
