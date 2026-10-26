export default {
  name: 'recipes',
  async next() {
    const r = Math.random()
    if (r < 0.35) return { method: 'GET', path: '/apps/recipes/?get_products=1' }
    if (r < 0.7) return { method: 'GET', path: '/apps/recipes/?get_recipes=1' }
    if (r < 0.85) return { method: 'GET', path: '/apps/recipes/i18n/load.php?lang=en' }
    return { method: 'GET', path: '/apps/recipes/?lang=en&theme=light' }
  },
}
