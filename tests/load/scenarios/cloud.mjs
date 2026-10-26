export default {
  name: 'cloud',
  async next() {
    const actions = ['get_state', 'get_file_tree', 'get_sync_log', 'get_storage_usage', 'get_folder_state']
    const action = actions[Math.floor(Math.random() * actions.length)]
    if (Math.random() < 0.2) return { method: 'GET', path: '/apps/cloud/?lang=en&theme=light' }
    if (Math.random() < 0.15) return { method: 'GET', path: '/apps/cloud/i18n/load.php?lang=en' }
    return { method: 'GET', path: `/apps/cloud/api.php?action=${action}` }
  },
}
