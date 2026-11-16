# Recipes — Recipe Management

**Web UI**: `/apps/recipes/` **API Base**: `/public-api/v1/recipes` **Auth**: Bearer + `recipes` app access

---

## User Functions

| Feature | Description |
|---------|-------------|
| **Recipe CRUD** | Title, description, prep/cook time, servings, difficulty, cuisine, diet tags |
| **Ingredients** | Amount, unit (g, ml, cups, pcs), ingredient name, notes, optional group (e.g., "For sauce") |
| **Steps** | Numbered instructions; timer support per step; photos per step |
| **Photos** | Cover image + step images; auto-thumbnails; drag-drop reorder |
| **Tags & Filters** | Custom tags (vegetarian, quick, meal-prep, etc.); filter by tag, time, ingredients |
| **Meal Planning** | Drag recipes to calendar (week view); auto-generate shopping list |
| **Shopping List** | Consolidated ingredients from meal plan; group by aisle/category; check off items |
| **Import from URL** | Paste recipe URL → auto-extract (schema.org, JSON-LD, microdata, Open Graph) |
| **Export** | Recipe cards (PDF), Meal plan (ICAL), Shopping list (TXT/CSV) |
| **Sharing** | Public link (read-only); embed code; QR code |
| **Scaling** | Adjust servings → auto-scale all ingredients |
| **Unit Conversion** | Metric ↔ Imperial toggle; per-ingredient override |
| **Nutrition** | Optional: calories, macros per serving (manual or API) |

---

## API Endpoints

### Recipes

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/recipes` | List recipes. Query: `search`, `tag`, `cuisine`, `diet`, `max_time`, `min_servings`, `max_servings`, `has_nutrition`, `cursor`, `limit`, `sort` (created/updated/title/time), `order` |
| `POST` | `/recipes` | Create recipe. Body: full recipe object (see Data Model) |
| `GET` | `/recipes/{id}` | Full recipe with ingredients, steps, photos, nutrition, tags |
| `PATCH` | `/recipes/{id}` | Update recipe (partial) |
| `DELETE` | `/recipes/{id}` | Delete recipe |
| `POST` | `/recipes/{id}/duplicate` | Clone recipe (new ID, "Copy of..." title) |
| `POST` | `/recipes/{id}/scale` | Scale servings. Body: `new_servings`. Returns scaled ingredients |
| `GET` | `/recipes/{id}/shopping-list` | Ingredients formatted for shopping (grouped by category) |
| `POST` | `/recipes/import` | Import from URL. Body: `url`, `auto_fetch_images?` |
| `POST` | `/recipes/import/bulk` | Import multiple URLs. Body: `urls[]` |

### Tags

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/tags` | All tags with recipe counts |
| `POST` | `/tags` | Create tag. Body: `name`, `color?`, `icon?` |
| `DELETE` | `/tags/{id}` | Delete tag (removes from recipes) |

### Meal Plan

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/meal-plan` | Get meal plan. Query: `date_from`, `date_to` (default: 7 days) |
| `POST` | `/meal-plan` | Add to meal plan. Body: `recipe_id`, `date`, `meal_type` (breakfast/lunch/dinner/snack), `servings?` |
| `PATCH` | `/meal-plan/{id}` | Update entry (move date, change meal_type, servings) |
| `DELETE` | `/meal-plan/{id}` | Remove from meal plan |
| `GET` | `/meal-plan/shopping-list` | Consolidated shopping list for date range. Query: `date_from`, `date_to`, `group_by` (category/recipe) |
| `POST` | `/meal-plan/auto-fill` | Auto-fill week. Body: `preferences` (tags, max_time, variety) |

### Public Shares

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/shares` | List your public shares |
| `POST` | `/shares` | Create share. Body: `recipe_id`, `password?`, `expires_at?`, `allow_print?` |
| `PATCH` | `/shares/{token}` | Update share |
| `DELETE` | `/shares/{token}` | Revoke share |

---

## Web Routes

| Path | Description |
|------|-------------|
| `/apps/recipes/` | Recipe manager: grid/list, meal plan calendar, shopping list, import dialog |

---

## MCP Tools (AI Assistant)

| Tool | Description |
|------|-------------|
| `list_recipes` | List recipes with filters |
| `get_recipe` | Full recipe details |
| `search_recipes` | Search by ingredient/title/tag |

---

## Data Model

```json
// Recipe
{
  "id": 123,
  "title": "Chicken Tikka Masala",
  "description": "Creamy tomato-based curry...",
  "prep_time_min": 20,
  "cook_time_min": 30,
  "servings": 4,
  "difficulty": "medium",        // easy | medium | hard
  "cuisine": "Indian",
  "diet_tags": ["gluten-free", "high-protein"],
  "tags": ["dinner", "meal-prep", "spicy"],
  "cover_image_url": "https://.../cover.jpg",
  "nutrition_per_serving": {
    "calories": 420,
    "protein_g": 35,
    "carbs_g": 18,
    "fat_g": 22,
    "fiber_g": 4
  },
  "ingredients": [
    {
      "id": 1,
      "group": "Marinade",
      "amount": 500,
      "unit": "g",
      "ingredient": "chicken breast",
      "notes": "cut into 2cm cubes",
      "order": 1
    },
    {
      "id": 2,
      "group": "Sauce",
      "amount": 400,
      "unit": "ml",
      "ingredient": "canned tomatoes",
      "notes": "crushed",
      "order": 2
    }
  ],
  "steps": [
    {
      "id": 1,
      "order": 1,
      "instruction": "Mix marinade ingredients. Add chicken. Refrigerate 30 min.",
      "timer_min": 30,
      "image_url": "https://.../step1.jpg"
    },
    {
      "id": 2,
      "order": 2,
      "instruction": "Grill chicken until charred. Set aside.",
      "timer_min": 10,
      "image_url": null
    }
  ],
  "created_at": "2026-01-10T14:20:00Z",
  "updated_at": "2026-01-15T10:00:00Z"
}

// Meal Plan Entry
{
  "id": 456,
  "recipe_id": 123,
  "date": "2026-01-20",
  "meal_type": "dinner",
  "servings": 4,
  "recipe": { ... }  // nested recipe object
}
```

---

## Import from URL (Supported Formats)

| Format | Selectors |
|--------|-----------|
| **JSON-LD** (`application/ld+json`) | `@type: Recipe` |
| **Microdata** (`itemprop`) | `itemtype="https://schema.org/Recipe"` |
| **RDFa** | `typeof="Recipe"` |
| **Open Graph** | `og:recipe` namespace |
| **Custom meta** | `recipeIngredient`, `recipeInstructions` meta tags |

**Supported sites**: Most food blogs, AllRecipes, FoodNetwork, BBC Good Food, SeriousEats, NYT Cooking, etc.

**Limitations**: Paywalled sites, JavaScript-rendered content, non-standard markup.

---

## Shopping List Format

```json
[
  {
    "category": "Produce",
    "items": [
      { "ingredient": "chicken breast", "amount": 500, "unit": "g", "recipes": ["Chicken Tikka Masala"] },
      { "ingredient": "onion", "amount": 2, "unit": "pcs", "recipes": ["Chicken Tikka Masala", "Curry"] }
    ]
  },
  {
    "category": "Pantry",
    "items": [
      { "ingredient": "canned tomatoes", "amount": 800, "unit": "ml", "recipes": ["Chicken Tikka Masala"] }
    ]
  }
]
```

---

## Configuration (Admin)

Admin → Configuration → **Recipes**:

| Setting | Description |
|---------|-------------|
| Default servings | For new recipes (default: 4) |
| Default difficulty | `easy` \| `medium` \| `hard` |
| Nutrition API | Enable + provider (Edamam, Spoonacular, USDA) + API key |
| Import timeout | Seconds for URL fetch (default: 30) |
| Max image size | Per recipe photo (default: 5 MB) |
| Allowed image types | `jpg`, `png`, `webp`, `heic` |
| Meal plan week start | `monday` \| `sunday` |
| Shopping list categories | Default aisle mapping (customizable per user) |

---

## Unit System

| Base Unit | Metric | Imperial |
|-----------|--------|----------|
| Weight | g, kg | oz, lb |
| Volume | ml, l | tsp, tbsp, cup, fl oz, pt, qt, gal |
| Count | pcs | pcs |
| Length | cm, mm | in |

**Conversion**: Automatic on user preference toggle. Per-ingredient lock available.

---

## Export Formats

| Format | Use Case |
|--------|----------|
| **Recipe Card (PDF)** | Printable 4×6 card with photo, ingredients, steps |
| **Meal Plan (ICAL)** | Import to Google Calendar, Apple Calendar, Outlook |
| **Shopping List (TXT/CSV)** | Phone notes, Todoist, Bring!, AnyList |
| **Full Backup (JSON)** | Migration, backup, API sync |

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| Import fails / empty recipe | Site uses JS rendering; try manual entry; check URL accessibility |
| Images not importing | Site blocks hotlinking; download manually → upload |
| Nutrition not calculating | Enable Nutrition API in Admin; verify API key; check ingredient names match USDA |
| Shopping list missing items | Check ingredient `unit` is recognized; "pcs" items need count |
| Meal plan not saving | Verify `date` format YYYY-MM-DD; `meal_type` enum; recipe exists |
| Scaling wrong | Check ingredient `unit` is convertible; "to taste" items don't scale |
| Tags not filtering | Tags are case-insensitive; check exact spelling |
| PDF export blank | Check wkhtmltopdf/Chromium in container; increase memory limit |