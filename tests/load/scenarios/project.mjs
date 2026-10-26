export default {
  name: 'project',
  async next() {
    const paths = ['/login', '/main', '/main?app=cloud', '/main?app=steps', '/main?app=spends']
    const path = paths[Math.floor(Math.random() * paths.length)]
    return { method: 'GET', path }
  },
}
