export default {
  name: 'smart_home',
  async next() {
    const r = Math.random()
    if (r < 0.25) return { method: 'GET', path: '/apps/smart_home/?lang=en&theme=light' }
    if (r < 0.35) return { method: 'GET', path: '/apps/smart_home/i18n/load.php?lang=en' }
    const action = r < 0.85 ? 'state' : (r < 0.95 ? 'monitor_stats' : 'metrics')
    return {
      method: 'POST',
      path: `/apps/smart_home/?api=${action}`,
      headers: { 'Content-Type': 'application/json' },
      body: '{}',
    }
  },
}
