export default {
  name: 'lopaka',
  async next() {
    const paths = ['/apps/lopaka/', '/apps/lopaka/index.html', '/main?app=lopaka']
    return { method: 'GET', path: paths[Math.floor(Math.random() * paths.length)] }
  },
}
