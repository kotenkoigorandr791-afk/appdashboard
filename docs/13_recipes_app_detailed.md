# Recipes (Recipe Manager) Application - Detailed Documentation

**Path:** `apps/recipes/`  
**Type:** Full Application  
**Description:** Personal recipe collection with meal planning and AI features.

---

## Overview

Recipes is a personal recipe manager that allows users to collect, organize, and search their favorite recipes with meal planning features.

---

## File Structure

```
apps/recipes/
├── index.php                     # Main entry point
├── controllers/                  # MVC controllers
│   ├── RecipesController.php
│   └── MealPlanController.php
├── core/                         # Core models, services, helpers
│   ├── RecipeModel.php
│   ├── IngredientService.php
│   └── MealPlanService.php
├── bg_remove.py                  # Background removal script (AI)
├── color-tone.js                 # Color palette utility
└── dark-background.jpg           # Theme background
```

## Configuration Files

| File | Purpose |
|------|---------|
| `dark-background.jpg` | Default theme background |
| `bg_remove.py` | AI background removal script (Python) |
| `color-tone.js` | Color palette utility (JavaScript) |

## Key Features

### Recipe Management
- Add recipes with ingredients and instructions
- Import from external sources
- Recipe categories and tags
- Rating and review system
- Scale serving sizes
- Print and export options

### Meal Planning
- Create weekly meal plans
- Generate shopping lists from recipes
- Recipe suggestions based on dietary preferences
- Meal history and favorites

### AI Features
- **bg_remove.py**: AI-powered background removal from recipe images
- **color-tone.js**: Auto-extract color palettes from recipe photos
- Recipe suggestions based on ingredients you have

### Data Storage
Recipes stored in PostgreSQL `recipes` collection/schema:

```
Schema: recipes
├── recipes     # Recipe documents
├── categories  # Recipe categories
├── ingredients # Ingredient list
└── meal_plans  # Planned meals
```

### Search
- Full-text search across recipe titles, ingredients, notes
- Filter by category, tags, preparation time
- Sort by rating, recently added, popularity

## API Endpoints

### Recipes
- `GET /api/recipes` - List recipes (with filters)
- `POST /api/recipes` - Create new recipe
- `GET /api/recipes/{id}` - Get recipe details
- `PUT /api/recipes/{id}` - Update recipe
- `DELETE /api/recipes/{id}` - Delete recipe

### Meal Plans
- `GET /api/meal-plans` - List meal plans
- `POST /api/meal-plans` - Create meal plan
- `PUT /api/meal-plans/{id}` - Update meal plan

### AI
- `POST /api/ai/remove-bg` - Remove background from image
- `POST /api/ai/color-palette` - Extract color palette

## Security Features

- Recipe access control per user
- Ingredient lists may have privacy settings
- Shopping list data isolation
- Image upload sanitization

## Performance

- Recipe search with database indexing
- Image caching for generated thumbnails
- Meal plan generation on-demand
- Background cleanup for orphaned data

## Internationalization

Supports all 6 interface languages (en, uk, ru, be, pl, cs).

## Dependencies

### Python
- Pillow: Image processing
- OpenCV: Computer vision

### PHP
- GuzzleHttp: API client
- PDO: Database access

## Deployment Notes

- Python 3.8+ required for bg_remove.py
- PostgreSQL recipe schema setup needed
- Write permissions for data directory
- Optional: ML frameworks for AI features
