export default {
  name: 'films',
  async next() {
    if (Math.random() < 0.35) return { method: 'GET', path: '/apps/films/?lang=en&theme=light' }
    return {
      method: 'POST',
      path: '/apps/films/?lang=en',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'get_movies' }),
    }
  },
}
