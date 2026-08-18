# Recipes Application - Detailed Documentation

**Path:** `apps/recipes/`  
**Total Lines of Code:** ~2,800  
**Main Entry:** `index.php` (2,740 lines)

---

## Table of Contents

1. [Overview](#overview)
2. [File Structure](#file-structure)
3. [Core Components](#core-components)
4. [Data Storage](#data-storage)
5. [Features](#features)
6. [API Reference](#api-reference)
7. [Frontend Architecture](#frontend-architecture)

---

## Overview

The Recipes application is a **recipe collection manager** with a product/ingredient database. It allows users to create, organize, and follow recipes with step-by-step instructions.

### Key Statistics

| Metric | Value |
|--------|-------|
| Total PHP Files | 3 |
| Total JSON Files | 80+ |
| Total Products | 37 |
| Total Recipes | 8 |
| Languages Supported | 4 (EN, UK, RU, BE) |
| Main File Size | 2,740 lines |

---

## File Structure

```
apps/recipes/
├── index.php                          # Main recipe interface (2,740 lines)
├── styles.php                         # Dynamic CSS styles (42 KB)
├── translations.json                  # UI translations (16 KB)
├── name.json                          # App name translations
├── recipe_state.json                  # Recipe state persistence (68 bytes)
├── icon.png                           # Application icon (89 KB)
├── Thumbs.db                          # Windows thumbnail cache
│
├── Background Images
│   ├── dark-background.jpg            # Dark theme background (128 KB)
│   ├── light-background.jpg           # Light theme background (7 KB)
│   └── default_recipe.jpg             # Default recipe image (8 KB)
│
├── products/                          # Product database (37 products)
│   └── {product_id}/
│       ├── image.{jpg,jpeg,png,webp}  # Product image
│       └── product.json               # Product metadata (~200 bytes)
│
└── recipes/                           # Recipe storage (8 recipes)
    └── {recipe_id}/
        ├── image.{jpg,webp}           # Recipe image (46-267 KB)
        └── recipe.json                # Recipe data (2-9 KB)
```

---

## Core Components

### 1. Main Interface (`index.php`)

**Purpose:** Recipe browsing, creation, and cooking mode interface.

**Key Functions:**

```php
// Database connection
function getDB(): ?SQLite3 {
    static $db = null;
    if ($db === null) {
        $db = new SQLite3(DB_FILE);
        $db->enableExceptions(true);
        $db->busyTimeout(3000);
    }
    return $db;
}

// User authentication
function get_current_user_from_sessions(): string
function getUserRoleByEmail(string $email): int

// Recipe operations
function loadRecipes(): array
function getRecipeById(string $id): ?array
function saveRecipe(array $recipe): bool
function deleteRecipe(string $id): bool

// Product operations
function loadProducts(): array
function getProductById(string $id): ?array
function saveProduct(array $product): bool
function deleteProduct(string $id): bool

// Recipe state
function loadRecipeState(): array
function saveRecipeState(array $state): bool
```

**URL Parameters:**
```php
$lang   = $_GET['lang']   ?? 'en';
$theme  = $_GET['theme']  ?? 'light';
$color  = $_GET['color']  ?? '#78a300';
```

---

### 2. Product Data Structure

**Product File (`products/{id}/product.json`):**
```json
{
    "id": "68c5c4921bcc1",
    "name": "Chicken Breast",
    "name_ru": "Куриная грудка",
    "name_uk": "Куряча грудка",
    "category": "meat",
    "unit": "g",
    "calories_per_100g": 165,
    "protein": 31,
    "fat": 3.6,
    "carbs": 0,
    "image": "image.jpg",
    "created_at": "2024-01-15T10:00:00Z"
}
```

**Product Categories:**
- meat
- fish
- vegetables
- fruits
- dairy
- grains
- spices
- oils
- beverages
- other

---

### 3. Recipe Data Structure

**Recipe File (`recipes/{id}/recipe.json`):**
```json
{
    "id": "68c89566beb7b",
    "title": "Grilled Chicken Salad",
    "title_ru": "Салат с жареной курицей",
    "title_uk": "Салат з смаженою куркою",
    "description": "A healthy and delicious grilled chicken salad",
    "prep_time": 15,
    "cook_time": 20,
    "servings": 4,
    "difficulty": "easy",
    "cuisine": "Mediterranean",
    "image": "image.jpg",
    "ingredients": [
        {
            "product_id": "68c5c4921bcc1",
            "name": "Chicken Breast",
            "amount": 500,
            "unit": "g"
        },
        {
            "product_id": "68c5c8b31dbcb",
            "name": "Lettuce",
            "amount": 200,
            "unit": "g"
        },
        {
            "product_id": "68c5c8c38b6c2",
            "name": "Tomatoes",
            "amount": 300,
            "unit": "g"
        }
    ],
    "steps": [
        {
            "order": 1,
            "instruction": "Season chicken breast with salt and pepper",
            "image": "step1.jpg",
            "time": 5
        },
        {
            "order": 2,
            "instruction": "Grill chicken for 6-7 minutes per side",
            "image": null,
            "time": 15
        },
        {
            "order": 3,
            "instruction": "Slice chicken and arrange on salad",
            "image": "step3.jpg",
            "time": 5
        }
    ],
    "nutrition": {
        "calories": 350,
        "protein": 40,
        "fat": 12,
        "carbs": 15
    },
    "tags": ["healthy", "protein", "low-carb"],
    "created_at": "2024-01-15T10:00:00Z",
    "updated_at": "2024-01-20T15:00:00Z"
}
```

---

### 4. Recipe State (`recipe_state.json`)

**Purpose:** Persist current cooking state (which recipe is being prepared, completed steps).

**Structure:**
```json
{
    "current_recipe": "68c89566beb7b",
    "completed_steps": [1, 2],
    "started_at": "2024-01-20T18:00:00Z",
    "timer_remaining": null
}
```

---

## Data Storage

### Storage Pattern

Each product and recipe has its own directory with a unique ID:

```
products/
└── {unique_id}/
    ├── product.json    # Metadata
    └── image.jpg       # Product image

recipes/
└── {unique_id}/
    ├── recipe.json     # Recipe data
    └── image.jpg       # Recipe image
```

### ID Generation

```php
function generateUniqueId(): string {
    return uniqid(); // e.g., "68c5c4921bcc1"
}
```

---

## Features

### 1. Recipe Browsing

**View Options:**
- Grid view with images
- List view with details
- Filter by category/cuisine/difficulty
- Sort by name, time, rating, date

**Recipe Card Display:**
- Thumbnail image
- Title
- Prep + Cook time
- Servings
- Difficulty badge
- Cuisine type

### 2. Recipe Creation/Editing

**Form Fields:**
- Title (multi-language)
- Description
- Prep time, Cook time
- Servings
- Difficulty (easy/medium/hard)
- Cuisine type
- Image upload
- Ingredients (product selector + amount)
- Steps (ordered, with optional images)
- Nutrition info
- Tags

### 3. Cooking Mode

**Features:**
- Full-screen mode
- Step-by-step navigation
- Step completion tracking
- Built-in timer for each step
- Ingredient checklist
- Auto-scroll to current step

### 4. Product Database

**Product Management:**
- Add/Edit/Delete products
- Product categories
- Nutritional information
- Product images
- Multi-language names

### 5. Search & Filter

**Search Options:**
- By recipe title
- By ingredients
- By tags
- By cuisine

**Filters:**
- Difficulty level
- Prep time range
- Cook time range
- Cuisine type
- Dietary tags (vegetarian, vegan, etc.)

### 6. Theme Support

**Themes:**
- Light theme with light background
- Dark theme with dark background

**Custom Colors:**
- Accent color picker
- Persistent theme preference

---

## API Reference

### Recipe Endpoints

#### Get All Recipes
```http
GET /index.php?action=get_recipes

Response:
{
    "success": true,
    "recipes": [
        {
            "id": "68c89566beb7b",
            "title": "Grilled Chicken Salad",
            "prep_time": 15,
            "cook_time": 20,
            "servings": 4,
            "difficulty": "easy",
            "image": "recipes/68c89566beb7b/image.jpg"
        }
    ]
}
```

#### Get Recipe Details
```http
GET /index.php?action=get_recipe&id=68c89566beb7b

Response:
{
    "success": true,
    "recipe": {
        "id": "68c89566beb7b",
        "title": "Grilled Chicken Salad",
        "ingredients": [...],
        "steps": [...]
    }
}
```

#### Create Recipe
```http
POST /index.php?action=create_recipe
Content-Type: application/json

{
    "title": "New Recipe",
    "description": "...",
    "prep_time": 15,
    "cook_time": 20,
    "servings": 4,
    "difficulty": "easy",
    "ingredients": [...],
    "steps": [...]
}
```

#### Update Recipe
```http
POST /index.php?action=update_recipe
Content-Type: application/json

{
    "id": "68c89566beb7b",
    "title": "Updated Title",
    ...
}
```

#### Delete Recipe
```http
POST /index.php?action=delete_recipe
Content-Type: application/json

{
    "id": "68c89566beb7b"
}
```

---

### Product Endpoints

#### Get All Products
```http
GET /index.php?action=get_products

Response:
{
    "success": true,
    "products": [
        {
            "id": "68c5c4921bcc1",
            "name": "Chicken Breast",
            "category": "meat",
            "unit": "g",
            "calories_per_100g": 165
        }
    ]
}
```

#### Create Product
```http
POST /index.php?action=create_product
Content-Type: application/json

{
    "name": "New Product",
    "category": "vegetables",
    "unit": "g",
    "calories_per_100g": 50
}
```

---

### Recipe State Endpoints

#### Get Current State
```http
GET /index.php?action=get_recipe_state

Response:
{
    "success": true,
    "state": {
        "current_recipe": "68c89566beb7b",
        "completed_steps": [1, 2]
    }
}
```

#### Save State
```http
POST /index.php?action=save_recipe_state
Content-Type: application/json

{
    "current_recipe": "68c89566beb7b",
    "completed_steps": [1, 2, 3]
}
```

---

## Frontend Architecture

### Main JavaScript Modules

```javascript
// Recipe Manager
class RecipeManager {
    constructor() {
        this.recipes = [];
        this.products = [];
        this.currentRecipe = null;
        this.cookingMode = false;
    }
    
    async loadRecipes() {
        const response = await fetch('?action=get_recipes');
        const data = await response.json();
        this.recipes = data.recipes;
        this.renderRecipes();
    }
    
    async loadProducts() {
        const response = await fetch('?action=get_products');
        const data = await response.json();
        this.products = data.products;
    }
    
    renderRecipes() {
        const container = document.getElementById('recipes-container');
        container.innerHTML = this.recipes.map(recipe => `
            <div class="recipe-card" data-id="${recipe.id}">
                <img src="${recipe.image}" alt="${recipe.title}">
                <div class="recipe-info">
                    <h3>${recipe.title}</h3>
                    <div class="recipe-meta">
                        <span>⏱ ${recipe.prep_time + recipe.cook_time} min</span>
                        <span>🍽 ${recipe.servings} servings</span>
                        <span class="difficulty ${recipe.difficulty}">${recipe.difficulty}</span>
                    </div>
                </div>
                <div class="recipe-actions">
                    <button onclick="recipeManager.viewRecipe('${recipe.id}')">View</button>
                    <button onclick="recipeManager.cookRecipe('${recipe.id}')">Cook</button>
                </div>
            </div>
        `).join('');
    }
    
    async viewRecipe(id) {
        const response = await fetch(`?action=get_recipe&id=${id}`);
        const data = await response.json();
        this.currentRecipe = data.recipe;
        this.showRecipeDetail();
    }
    
    showRecipeDetail() {
        const recipe = this.currentRecipe;
        const container = document.getElementById('recipe-detail');
        
        container.innerHTML = `
            <div class="recipe-header">
                <img src="${recipe.image}" alt="${recipe.title}">
                <h1>${recipe.title}</h1>
                <p>${recipe.description}</p>
            </div>
            
            <div class="recipe-meta">
                <div>Prep: ${recipe.prep_time} min</div>
                <div>Cook: ${recipe.cook_time} min</div>
                <div>Servings: ${recipe.servings}</div>
                <div>Difficulty: ${recipe.difficulty}</div>
            </div>
            
            <div class="ingredients">
                <h2>Ingredients</h2>
                <ul>
                    ${recipe.ingredients.map(ing => `
                        <li>${ing.amount} ${ing.unit} ${ing.name}</li>
                    `).join('')}
                </ul>
            </div>
            
            <div class="steps">
                <h2>Instructions</h2>
                ${recipe.steps.map(step => `
                    <div class="step">
                        <div class="step-number">${step.order}</div>
                        <div class="step-content">
                            <p>${step.instruction}</p>
                            ${step.time ? `<span class="step-time">${step.time} min</span>` : ''}
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    }
    
    cookRecipe(id) {
        this.currentRecipeId = id;
        this.currentStep = 0;
        this.cookingMode = true;
        this.showCookingMode();
    }
    
    showCookingMode() {
        const recipe = this.currentRecipe;
        const container = document.getElementById('cooking-mode');
        
        container.innerHTML = `
            <div class="cooking-header">
                <h2>${recipe.title}</h2>
                <button onclick="recipeManager.exitCookingMode()">Exit</button>
            </div>
            
            <div class="step-display">
                <div class="step-image">
                    ${recipe.steps[this.currentStep].image ? 
                        `<img src="${recipe.steps[this.currentStep].image}">` : ''}
                </div>
                <div class="step-instruction">
                    <h3>Step ${recipe.steps[this.currentStep].order}</h3>
                    <p>${recipe.steps[this.currentStep].instruction}</p>
                    ${recipe.steps[this.currentStep].time ? 
                        `<button onclick="recipeManager.startTimer(${recipe.steps[this.currentStep].time})">
                            Start Timer (${recipe.steps[this.currentStep].time} min)
                        </button>` : ''}
                </div>
            </div>
            
            <div class="step-navigation">
                <button onclick="recipeManager.prevStep()" ${this.currentStep === 0 ? 'disabled' : ''}>
                    Previous
                </button>
                <button onclick="recipeManager.nextStep()">
                    Next
                </button>
            </div>
        `;
    }
    
    nextStep() {
        if (this.currentStep < this.currentRecipe.steps.length - 1) {
            this.currentStep++;
            this.showCookingMode();
            this.saveState();
        }
    }
    
    prevStep() {
        if (this.currentStep > 0) {
            this.currentStep--;
            this.showCookingMode();
            this.saveState();
        }
    }
    
    async saveState() {
        await fetch('?action=save_recipe_state', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                current_recipe: this.currentRecipeId,
                completed_steps: this.currentRecipe.steps
                    .slice(0, this.currentStep + 1)
                    .map(s => s.order)
            })
        });
    }
    
    exitCookingMode() {
        this.cookingMode = false;
        document.getElementById('cooking-mode').innerHTML = '';
    }
}

// Initialize
const recipeManager = new RecipeManager();
document.addEventListener('DOMContentLoaded', () => {
    recipeManager.loadRecipes();
    recipeManager.loadProducts();
});
```

---

## Security

### File Upload Validation

```php
function validateImageUpload($file): array {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowedTypes)) {
        return ['valid' => false, 'error' => 'Invalid file type'];
    }
    
    if ($file['size'] > $maxSize) {
        return ['valid' => false, 'error' => 'File too large'];
    }
    
    // Check if it's actually an image
    $check = getimagesize($file['tmp_name']);
    if ($check === false) {
        return ['valid' => false, 'error' => 'Not a valid image'];
    }
    
    return ['valid' => true];
}
```

### Path Sanitization

```php
function sanitizeId($id): string {
    // Only allow alphanumeric characters
    return preg_replace('/[^a-zA-Z0-9]/', '', $id);
}
```

---

## Troubleshooting

### Common Issues

| Issue | Cause | Solution |
|-------|-------|----------|
| Images not showing | Wrong path | Check image paths in JSON |
| Recipe not saving | JSON error | Validate JSON syntax |
| Cooking mode stuck | State file locked | Delete recipe_state.json |
| Products not loading | Empty products folder | Add at least one product |

---

**Document Version:** 1.0  
**Last Updated:** 2026-04-02  
**Maintained By:** ServerKherson Development Team
