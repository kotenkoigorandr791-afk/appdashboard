<script>
// Анимация созвездий на фоне
(function() {
    const canvas = document.getElementById('constellation-bg');
    if (!canvas) return;
    
    const ctx = canvas.getContext('2d');
    let stars = [];
    const STAR_COUNT = 100; // количество звезд
    const primaryColor = '<?= $color ?>'; // цвет из ссылки
    
    // Функция для получения цвета с прозрачностью
    function getColorWithOpacity(opacity) {
        // Парсим HEX в RGB
        let r, g, b;
        if (primaryColor.startsWith('#')) {
            const hex = primaryColor.slice(1);
            if (hex.length === 3) {
                r = parseInt(hex[0] + hex[0], 16);
                g = parseInt(hex[1] + hex[1], 16);
                b = parseInt(hex[2] + hex[2], 16);
            } else {
                r = parseInt(hex.substring(0, 2), 16);
                g = parseInt(hex.substring(2, 4), 16);
                b = parseInt(hex.substring(4, 6), 16);
            }
        } else {
            // Если не HEX, используем цвет по умолчанию
            r = 133; g = 181; b = 3;
        }
        return `rgba(${r}, ${g}, ${b}, ${opacity})`;
    }
    
    function resize() {
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
        // При изменении размера пересоздаем звезды для равномерного распределения
        initStars();
    }
    
    window.addEventListener('resize', resize);
    
    function initStars() {
        stars = [];
        for (let i = 0; i < STAR_COUNT; i++) {
            stars.push({
                x: Math.random() * canvas.width,
                y: Math.random() * canvas.height,
                vx: (Math.random() - 0.5) * 0.15,
                vy: (Math.random() - 0.5) * 0.15
            });
        }
    }
    
    function updateStars() {
        for (let i = 0; i < stars.length; i++) {
            const s = stars[i];
            s.x += s.vx;
            s.y += s.vy;
            
            // Зацикливание по краям экрана с плавным переходом
            if (s.x < 0) s.x = canvas.width;
            if (s.x > canvas.width) s.x = 0;
            if (s.y < 0) s.y = canvas.height;
            if (s.y > canvas.height) s.y = 0;
        }
    }
    
    function draw() {
        // Очищаем canvas
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        
        // Рисуем линии между звездами
        ctx.lineWidth = 0.5;
        
        for (let i = 0; i < stars.length; i++) {
            for (let j = i + 1; j < stars.length; j++) {
                const dx = stars[i].x - stars[j].x;
                const dy = stars[i].y - stars[j].y;
                const dist = Math.sqrt(dx * dx + dy * dy);
                
                // Рисуем линию, если звезды достаточно близко
                if (dist < 140) {
                    // Прозрачность зависит от расстояния
                    const opacity = 0.2 * (1 - dist / 140);
                    ctx.strokeStyle = getColorWithOpacity(opacity);
                    ctx.beginPath();
                    ctx.moveTo(stars[i].x, stars[i].y);
                    ctx.lineTo(stars[j].x, stars[j].y);
                    ctx.stroke();
                }
            }
        }
        
        // Рисуем звезды
        for (let i = 0; i < stars.length; i++) {
            ctx.fillStyle = getColorWithOpacity(0.4);
            ctx.beginPath();
            ctx.arc(stars[i].x, stars[i].y, 1.5, 0, Math.PI * 2);
            ctx.fill();
        }
        
        updateStars();
        requestAnimationFrame(draw);
    }
    
    // Инициализация с текущими размерами
    resize();
    draw();
})();
</script>
