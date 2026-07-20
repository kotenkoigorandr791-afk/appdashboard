window.RecipeColorTone = (() => {
    const clamp = value => Math.max(0, Math.min(255, Math.round(value)));
    const parse = hex => {
        const clean = String(hex || '').replace('#', '');
        return [0, 2, 4].map(index => parseInt(clean.slice(index, index + 2), 16) || 0);
    };
    const hex = rgb => `#${rgb.map(value => clamp(value).toString(16).padStart(2, '0')).join('')}`;
    const mix = (base, target, amount) => {
        const a = parse(base);
        const b = parse(target);
        return hex(a.map((value, index) => value * (1 - amount) + b[index] * amount));
    };
    const shade = (base, factor) => hex(parse(base).map(value => value * factor));
    const apply = ({ color, theme }) => {
        const root = document.documentElement;
        const primary = color || getComputedStyle(root).getPropertyValue('--primary-color').trim() || '#78a300';
        const dark = theme === 'dark' || document.body.classList.contains('dark');
        root.style.setProperty('--photo-border-color', mix(shade(primary, dark ? 1.22 : 1.08), dark ? '#ffd166' : '#00a6a6', 0.46));
        root.style.setProperty('--recipe-line-border-color', mix(primary, dark ? '#8fd3ff' : '#006d77', 0.34));
        root.style.setProperty('--recipe-item-border-color', mix(primary, dark ? '#ffb86b' : '#b45f06', 0.28));
        root.style.setProperty('--btn-border-color', shade(primary, 0.58));
        root.style.setProperty('--btn-gradient-end', shade(primary, 0.8));
    };
    return { apply, mix, shade };
})();
