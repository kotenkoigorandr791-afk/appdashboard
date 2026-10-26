export default {
  name: 'spends',
  async next() {
    const year = new Date().getFullYear()
    const r = Math.random()
    if (r < 0.25) return { method: 'GET', path: '/apps/spends/?lang=en&theme=light' }
    if (r < 0.4) return { method: 'GET', path: '/apps/spends/i18n/load.php?lang=en' }
    if (r < 0.6) return { method: 'GET', path: `/apps/spends/?year_data=1&year=${year}` }
    if (r < 0.75) return { method: 'GET', path: '/apps/spends/?get_available_years=1' }
    if (r < 0.8) return { method: 'GET', path: '/apps/spends/?get_settings=1' }
    if (r < 0.85) return { method: 'GET', path: '/apps/spends/?get_exchange_rates=1&base=USD' }
    if (r < 0.9) return { method: 'GET', path: '/apps/spends/?get_tags=1' }
    if (r < 0.95) return { method: 'GET', path: '/apps/spends/?get_goals=1' }
    return { method: 'GET', path: '/apps/spends/?get_expense_meta=1' }
  },
}
