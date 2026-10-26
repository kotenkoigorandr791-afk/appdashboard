export default {
  name: 'steps',
  async next() {
    const r = Math.random()
    if (r < 0.25) return { method: 'GET', path: '/apps/steps/?lang=en&theme=light' }
    if (r < 0.4) return { method: 'GET', path: '/apps/steps/i18n/load.php?lang=en' }
    if (r < 0.7) {
      return {
        method: 'POST',
        path: '/apps/steps/',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'get_steps_updates=1&version=',
      }
    }
    return {
      method: 'POST',
      path: '/apps/steps/',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'get_table_page=1&page=1',
    }
  },
}
