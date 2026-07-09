// Fetch AI models from server config
export async function fetchModels() {
    try {
        const response = await fetch('/main?api=cloud_ai_config');
        const data = await response.json();
        return data.config?.models || [];
    } catch {
        return [];
    }
}

// Save selected model for an app
export function saveSelectedModel(app, model) {
    localStorage.setItem(`ai_model_${app}`, model);
}

// Get selected model for an app
export function getSelectedModel(app) {
    return localStorage.getItem(`ai_model_${app}`);
}