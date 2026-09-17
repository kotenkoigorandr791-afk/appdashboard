<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $t['app_title']; ?></title>
    <link rel="stylesheet" href="https://cdn.plyr.io/3.7.8/plyr.css" />
    <style>
        :root {
            --primary-color: <?php echo $color; ?>;
            --primary-color-rgb: <?php $hex = ltrim($color, '#'); $rgb = sscanf($hex, "%02x%02x%02x"); echo implode(', ', $rgb); ?>;
            --primary-light: <?php $hex = ltrim($color, '#'); echo '#' . max(1, hexdec(substr($hex,0,2)) + 40) . max(1, hexdec(substr($hex,2,2)) + 40) . max(1, hexdec(substr($hex,4,2)) + 40); ?>;
            --primary-dark: <?php $hex = ltrim($color, '#'); echo '#' . max(1, hexdec(substr($hex,0,2)) - 40) . max(1, hexdec(substr($hex,2,2)) - 40) . max(1, hexdec(substr($hex,4,2)) - 40); ?>;
            --shadow-glow: 0 0 20px rgba(<?php echo implode(', ', $rgb); ?>, 0.3);
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 24px;
        }

        /* Light Theme (default) */
        body[data-theme="light"] {
            --glass-bg: rgba(255, 255, 255, 0.5);
            --glass-bg-light: rgba(240, 240, 245, 0.55);
            --glass-border: rgba(0, 0, 0, 0.05);
            --glass-highlight: rgba(0, 0, 0, 0.02);
            --card-bg: rgba(255, 255, 255, 0.55);
            --text-primary: #1a1a1a;
            --text-secondary: rgba(0, 0, 0, 0.7);
            --text-muted: rgba(0, 0, 0, 0.4);
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 16px rgba(0, 0, 0, 0.15);
            --shadow-lg: 0 8px 32px rgba(0, 0, 0, 0.2);
        }

        /* Dark Theme */
        body[data-theme="dark"] {
            --glass-bg: rgba(15, 15, 25, 0.85);
            --glass-bg-light: rgba(25, 25, 40, 0.7);
            --glass-border: rgba(255, 255, 255, 0.1);
            --glass-highlight: rgba(255, 255, 255, 0.05);
            --card-bg: rgba(20, 20, 35, 0.7);
            --text-primary: #ffffff;
            --text-secondary: rgba(255, 255, 255, 0.7);
            --text-muted: rgba(255, 255, 255, 0.4);
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.3);
            --shadow-md: 0 4px 16px rgba(0, 0, 0, 0.4);
            --shadow-lg: 0 8px 32px rgba(0, 0, 0, 0.5);
        }

        /* Default to light theme if not specified */
        body {
            --glass-bg: rgba(255, 255, 255, 0.5);
            --glass-bg-light: rgba(240, 240, 245, 0.55);
            --glass-border: rgba(0, 0, 0, 0.05);
            --glass-highlight: rgba(0, 0, 0, 0.02);
            --card-bg: rgba(255, 255, 255, 0.55);
            --text-primary: #1a1a1a;
            --text-secondary: rgba(0, 0, 0, 0.7);
            --text-muted: rgba(0, 0, 0, 0.4);
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 16px rgba(0, 0, 0, 0.15);
            --shadow-lg: 0 8px 32px rgba(0, 0, 0, 0.2);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Netflix Sans', 'Helvetica Neue', Helvetica, Arial, sans-serif;
            transition: all 0.3s ease;
            min-height: 100vh;
            background: url('background.png') center center / cover no-repeat fixed;
            color: var(--text-primary);
            overflow-x: hidden;
        }

        body.light-theme {
            --glass-bg: rgba(255, 255, 255, 0.5);
            --glass-bg-light: rgba(240, 240, 245, 0.55);
            --glass-border: rgba(0, 0, 0, 0.05);
            --glass-highlight: rgba(0, 0, 0, 0.02);
            --card-bg: rgba(255, 255, 255, 0.55);
            --text-primary: #1a1a1a;
            --text-secondary: rgba(0, 0, 0, 0.7);
            --text-muted: rgba(0, 0, 0, 0.4);
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 16px rgba(0, 0, 0, 0.15);
            --shadow-lg: 0 8px 32px rgba(0, 0, 0, 0.2);
        }

        body.dark-theme {
            --glass-bg: rgba(15, 15, 25, 0.85);
            --glass-bg-light: rgba(25, 25, 40, 0.7);
            --glass-border: rgba(255, 255, 255, 0.1);
            --glass-highlight: rgba(255, 255, 255, 0.05);
            --card-bg: rgba(20, 20, 35, 0.7);
            --text-primary: #ffffff;
            --text-secondary: rgba(255, 255, 255, 0.7);
            --text-muted: rgba(255, 255, 255, 0.4);
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.3);
            --shadow-md: 0 4px 16px rgba(0, 0, 0, 0.4);
            --shadow-lg: 0 8px 32px rgba(0, 0, 0, 0.5);
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(ellipse at 30% 20%, rgba(<?php echo implode(', ', $rgb); ?>, 0.12) 0%, transparent 50%),
                        radial-gradient(ellipse at 70% 80%, rgba(<?php echo implode(', ', $rgb); ?>, 0.08) 0%, transparent 50%);
            animation: bgPulse 15s ease-in-out infinite;
            pointer-events: none;
            z-index: 0;
        }

        @keyframes bgPulse {
            0%, 100% { transform: scale(1) rotate(0deg); opacity: 0.5; }
            50% { transform: scale(1.1) rotate(5deg); opacity: 0.8; }
        }

        /* Slide-out Navigation */
        .nav-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(4px);
            z-index: 999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .nav-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .side-nav {
            position: fixed;
            top: 0;
            right: -320px;
            width: 320px;
            height: 100vh;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border-left: 1px solid var(--glass-border);
            z-index: 1000;
            transition: right 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            box-shadow: var(--shadow-lg);
        }

        .side-nav.active {
            right: 0;
        }

        .nav-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--glass-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .nav-logo {
            font-size: 1.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .nav-close {
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 0.5rem;
            border-radius: var(--radius-sm);
            transition: all 0.3s ease;
        }

        .nav-close:hover {
            background: var(--glass-highlight);
            color: var(--text-primary);
        }

        .nav-menu {
            flex: 1;
            padding: 1rem 0;
            overflow-y: auto;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 1.5rem;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
            cursor: pointer;
        }

        .nav-item:hover {
            background: var(--glass-highlight);
            color: var(--text-primary);
            border-left-color: var(--primary-color);
        }

        .nav-item.active {
            background: rgba(<?php echo implode(', ', $rgb); ?>, 0.15);
            color: var(--text-primary);
            border-left-color: var(--primary-color);
        }

        .nav-item svg {
            width: 22px;
            height: 22px;
            fill: currentColor;
        }

        .nav-footer {
            padding: 1.5rem;
            border-top: 1px solid var(--glass-border);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            background: var(--glass-highlight);
            border-radius: var(--radius-md);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1.1rem;
            overflow: hidden;
            flex-shrink: 0;
        }
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        .user-details {
            flex: 1;
        }

        .user-name {
            font-weight: 600;
            font-size: 0.9rem;
        }

        .user-level {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        /* Header */
        .header {
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 100;
            width: 100vw;
            left: 50%;
            margin-left: -50vw;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--glass-border);
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .app-title {
            font-size: 1.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--text-primary), var(--primary-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .header-controls {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .menu-toggle {
            background: var(--glass-bg-light);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            color: var(--text-primary);
            cursor: pointer;
            padding: 0.6rem;
            border-radius: var(--radius-md);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .menu-toggle:hover {
            background: rgba(<?php echo implode(', ', $rgb); ?>, 0.2);
            border-color: var(--primary-color);
            transform: translateY(-2px);
        }

        .menu-toggle svg {
            width: 24px;
            height: 24px;
            fill: currentColor;
        }

        .menu-toggle.floating {
            position: fixed;
            top: 0.5%;
            right: 2%;
            z-index: 1000;
            width: 50px;
            height: 50px;
            padding: 0;
            border-radius: 50%;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            box-shadow: var(--shadow-md);
            cursor: pointer;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }

        .menu-toggle.floating:hover {
            background: rgba(<?php echo implode(', ', $rgb); ?>, 0.3);
            border-color: var(--primary-color);
        }

        .menu-toggle.floating.hidden {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }

        .menu-toggle.floating svg {
            width: 24px;
            height: 24px;
            fill: var(--text-primary);
        }

        .container { max-width: 1400px; margin: 0 auto; padding: 0 2rem; position: relative; z-index: 1; }

        /* Buttons */
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            white-space: nowrap;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s ease;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn--primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            box-shadow: 0 4px 15px rgba(<?php echo implode(', ', $rgb); ?>, 0.4);
        }

        .btn--primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(<?php echo implode(', ', $rgb); ?>, 0.5);
        }

        .btn--outline {
            background: transparent;
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
        }

        .btn--outline:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
        }

        .btn--secondary {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-primary);
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
        }

        .btn--secondary:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .btn--icon {
            background: linear-gradient(135deg, #28a745, #1e7e34);
            color: white;
            padding: 0.6rem;
            border-radius: var(--radius-md);
            font-size: 0.85rem;
            min-width: 40px;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        }

        .btn--icon:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
        }

        .btn--danger {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
        }

        .btn--danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(220, 53, 69, 0.4);
        }

        .btn--disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }

        .form-control {
            padding: 0.75rem 1rem;
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            width: 100%;
            background: var(--glass-bg-light);
            border: 1px solid var(--glass-border);
            color: var(--text-primary);
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(<?php echo implode(', ', $rgb); ?>, 0.2);
        }

        .form-control::placeholder {
            color: var(--text-muted);
        }

        /* Movies Grid */
        .movies-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 2rem;
            padding: 2rem 2%;
        }

        .movie-card {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid var(--glass-border);
            position: relative;
            overflow: hidden;
        }

        .movie-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-color), var(--primary-light), var(--primary-color));
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .movie-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: var(--shadow-lg), var(--shadow-glow);
            border-color: rgba(<?php echo implode(', ', $rgb); ?>, 0.3);
        }

        .movie-card:hover::before {
            opacity: 1;
        }

        .movie-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            line-height: 1.4;
            padding-right: 24px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .movie-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .movie-category {
            background: rgba(<?php echo implode(', ', $rgb); ?>, 0.2);
            color: var(--text-primary);
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            border: 1px solid rgba(<?php echo implode(', ', $rgb); ?>, 0.3);
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
        }

        body.light-theme .movie-category {
            color: #1a1a1a;
            background: rgba(<?php echo implode(', ', $rgb); ?>, 0.25);
            text-shadow: none;
        }

        .rating { display: flex; gap: 3px; margin-bottom: 1rem; }
        .star { color: #ffd700; font-size: 1.1rem; text-shadow: 0 0 10px rgba(255, 215, 0, 0.5); }

        body.light-theme .star {
            color: #c4a800;
            text-shadow: 0 0 10px rgba(196, 168, 0, 0.4);
        }

        .overall-rating {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
            font-size: 0.9rem;
            color: var(--text-primary);
        }

        .overall-rating-text { font-weight: 600; }
        
        .overall-rating span:last-child,
        .rating-value {
            font-weight: 700;
            font-size: 1rem;
            color: #ffd700;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
        }

        body.light-theme .overall-rating {
            color: var(--text-primary);
        }

        body.light-theme .overall-rating span:last-child,
        body.light-theme .rating-value {
            color: #c4a800;
            text-shadow: none;
        }

        .movie-actions {
            display: flex;
            gap: 0.75rem;
            align-items: center;
            margin-top: 1rem;
        }

        .watch-btn {
            flex: 1;
            padding: 0.875rem;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 15px rgba(<?php echo implode(', ', $rgb); ?>, 0.3);
        }

        .watch-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(<?php echo implode(', ', $rgb); ?>, 0.5);
        }

        .comments-btn {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-primary);
            padding: 0.75rem 1rem;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .comments-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        /* Modals */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(8px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .modal.show {
            display: flex;
            opacity: 1;
        }

        .modal-content {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border-radius: var(--radius-xl);
            padding: 2rem;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            border: 1px solid var(--glass-border);
            box-shadow: var(--shadow-lg);
            transform: scale(0.9);
            transition: transform 0.3s ease;
        }

        .modal.show .modal-content {
            transform: scale(1);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--glass-border);
        }

        .modal-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
        }

        .modal-close {
            background: var(--glass-highlight);
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-secondary);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .modal-close:hover {
            background: rgba(220, 53, 69, 0.2);
            color: #dc3545;
            transform: rotate(90deg);
        }

        .form-group { margin-bottom: 1.25rem; }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-secondary);
        }

        .rating-input { display: flex; gap: 0.5rem; margin-top: 0.5rem; }
        .rating-star {
            font-size: 1.75rem;
            color: rgba(255, 255, 255, 0.2);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .rating-star.active, .rating-star.hover {
            color: #ffd700;
            text-shadow: 0 0 15px rgba(255, 215, 0, 0.6);
            transform: scale(1.1);
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid var(--glass-border);
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-secondary);
            grid-column: 1 / -1;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .loading { display: none; text-align: center; padding: 2rem; }
        .loading.show { display: block; }

        /* Settings Button */
        .settings-btn {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 20px rgba(<?php echo implode(', ', $rgb); ?>, 0.5);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .settings-btn:hover {
            transform: scale(1.1) rotate(90deg);
            box-shadow: 0 6px 30px rgba(<?php echo implode(', ', $rgb); ?>, 0.6);
        }

        .settings-btn svg {
            width: 28px;
            height: 28px;
            fill: currentColor;
        }

        .settings-btn--disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }

        /* Settings Table */
        .settings-table {
            width: 100%;
            border-collapse: collapse;
            border-radius: var(--radius-md);
            overflow: hidden;
            background: var(--glass-bg-light);
        }

        .settings-table th, .settings-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--glass-border);
        }

        .settings-table th {
            background: rgba(<?php echo implode(', ', $rgb); ?>, 0.1);
            font-weight: 600;
            color: var(--primary-light);
        }

        .settings-table tr:hover {
            background: var(--glass-highlight);
        }

        .visibility-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: var(--radius-sm);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .visibility-btn:hover {
            background: rgba(<?php echo implode(', ', $rgb); ?>, 0.2);
        }

        .visibility-btn svg { width: 22px; height: 22px; }
        .visibility-btn.visible svg { fill: #28a745; filter: drop-shadow(0 0 5px rgba(40, 167, 69, 0.5)); }
        .visibility-btn.hidden svg { fill: var(--text-muted); }

        .content-type-icon {
            position: absolute;
            top: 1rem;
            right: 1rem;
            width: 24px;
            height: 24px;
            opacity: 0.6;
            transition: opacity 0.3s ease;
        }

        .movie-card:hover .content-type-icon {
            opacity: 1;
        }

        .content-type-icon svg { width: 100%; height: 100%; fill: var(--text-secondary); }

        /* Video Player */
        .video-player-modal .modal-content {
            padding: 0;
            background: transparent;
            width: 100vw;
            height: 100vh;
            max-width: none;
            max-height: none;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border-radius: 0;
            border: none;
            box-shadow: none;
        }

        .video-container {
            position: relative;
            width: 100%;
            height: 100%;
            max-width: 100%;
            max-height: 100%;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #000;
        }

        .video-container video { width: 100%; height: 100%; object-fit: contain; }

        .copy-btn svg, .settings-btn svg, .content-type-icon svg { width: 20px; height: 20px; fill: currentColor; }

        .video-player-close {
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 1.5rem;
            cursor: pointer;
            color: white;
            position: absolute;
            top: 20px;
            right: 20px;
            z-index: 11;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .video-player-close:hover {
            background: rgba(220, 53, 69, 0.8);
            transform: scale(1.1);
        }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 10px; height: 10px; }
        ::-webkit-scrollbar-track { background: rgba(0, 0, 0, 0.3); }
        ::-webkit-scrollbar-thumb {
            background: var(--primary-dark);
            border-radius: 5px;
        }
        ::-webkit-scrollbar-thumb:hover { background: var(--primary-color); }

        /* Loading Button */
        .btn--loading {
            position: relative;
            color: transparent !important;
        }

        .btn--loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            top: 50%;
            left: 50%;
            margin-left: -10px;
            margin-top: -10px;
            border: 2px solid #ffffff;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        .btn--outline.btn--loading::after { border: 2px solid var(--primary-color); border-top-color: transparent; }

        /* Search & Sort */
        .search-sort-container {
            display: flex;
            gap: 1rem;
            margin: 2rem 0;
            align-items: center;
            justify-content: flex-end;
            flex-wrap: wrap;
        }

        .search-box {
            width: 100%;
            max-width: 400px;
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 0.875rem 3.5rem 0.875rem 1.25rem;
            border-radius: var(--radius-lg);
            font-size: 0.95rem;
            border: 1px solid var(--glass-border);
            background: var(--glass-bg-light);
            backdrop-filter: blur(10px);
            color: var(--text-primary);
            transition: all 0.3s ease;
        }

        .search-input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(<?php echo implode(', ', $rgb); ?>, 0.15);
            background: rgba(<?php echo implode(', ', $rgb); ?>, 0.1);
        }

        .search-input::placeholder {
            color: var(--text-muted);
        }

        .search-icon {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            pointer-events: none;
            transition: color 0.3s ease;
        }

        .search-input:focus + .search-icon {
            color: var(--primary-color);
        }

        .sort-btn {
            background: var(--glass-bg-light);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            color: var(--text-primary);
            padding: 0.875rem;
            border-radius: var(--radius-lg);
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 48px;
            height: 48px;
        }

        .sort-btn:hover {
            background: rgba(<?php echo implode(', ', $rgb); ?>, 0.2);
            border-color: var(--primary-color);
            transform: translateY(-2px);
        }

        .sort-dropdown {
            position: absolute;
            top: calc(100% + 0.5rem);
            right: 0;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-lg);
            padding: 0.5rem;
            min-width: 220px;
            z-index: 100;
            box-shadow: var(--shadow-lg);
        }

        .sort-option {
            padding: 0.75rem 1rem;
            cursor: pointer;
            border-radius: var(--radius-sm);
            transition: all 0.3s ease;
            color: var(--text-primary);
            font-weight: 500;
        }

        .sort-option:hover {
            background: var(--glass-highlight);
            color: var(--text-primary);
        }

        .sort-option.active {
            background: rgba(<?php echo implode(', ', $rgb); ?>, 0.25);
            color: var(--text-primary);
            font-weight: 700;
        }

        body.light-theme .sort-option {
            color: #1a1a1a;
        }
        
        body.light-theme .sort-option.active {
            color: #1a1a1a;
        }

        .sort-container {
            position: relative;
            flex-shrink: 0;
        }

        .no-results {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--text-secondary);
            grid-column: 1 / -1;
        }

        /* Comments */
        .comments-section {
            margin-top: 1rem;
            padding: 1rem;
            border-radius: var(--radius-md);
            background: var(--glass-bg-light);
        }

        .comments-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .comments-list {
            max-height: 400px;
            overflow-y: auto;
            margin-bottom: 1rem;
        }

        .comment-item {
            padding: 1rem;
            border-bottom: 1px solid var(--glass-border);
            transition: background 0.3s ease;
        }

        .comment-item:hover {
            background: var(--glass-highlight);
        }

        .comment-item:last-child { border-bottom: none; }

        .comment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .comment-user {
            font-weight: 600;
            color: var(--primary-light);
        }

        .comment-date { font-size: 0.8rem; color: var(--text-muted); }
        .comment-rating { display: flex; gap: 2px; margin-bottom: 0.5rem; }
        .comment-rating .star { font-size: 1rem; }
        .comment-text { line-height: 1.5; color: var(--text-secondary); }
        .comment-actions { margin-top: 0.5rem; display: flex; justify-content: flex-end; }

        .comment-form {
            padding: 1rem;
            border-radius: var(--radius-md);
            background: var(--glass-bg-light);
            margin-top: 1rem;
        }

        .comment-form .form-group { margin-bottom: 1rem; }
        .comment-form .form-control { padding: 0.75rem; font-size: 0.9rem; }

        .comment-rating-input {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .comment-rating-stars { display: flex; gap: 0.25rem; }
        .comment-rating-star {
            font-size: 1.4rem;
            color: rgba(255, 255, 255, 0.2);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .comment-rating-star.active, .comment-rating-star.hover {
            color: #ffd700;
            text-shadow: 0 0 10px rgba(255, 215, 0, 0.5);
        }

        /* Plyr Customization */
        .plyr__video-wrapper { position: relative; width: 100%; height: 100%; }
        .plyr { width: 100%; height: 100%; max-height: 100vh; }

        .plyr--full-ui {
            background: #000;
        }

        .plyr__control {
            border-radius: var(--radius-sm);
            transition: all 0.3s ease;
        }

        .plyr__control:hover {
            background: var(--primary-color);
            transform: scale(1.1);
        }

        .plyr__progress__thumb {
            background: var(--primary-color);
        }

        .plyr--video .plyr__controls {
            background: linear-gradient(transparent, rgba(0,0,0,0.8));
        }

        .video-info-overlay {
            position: absolute;
            top: 80px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0,0,0,0.8);
            backdrop-filter: blur(8px);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius-lg);
            z-index: 10;
            font-size: 1rem;
            text-align: center;
            max-width: 80%;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            transition: opacity 0.3s ease, visibility 0.3s ease;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .video-player-modal.show .video-info-overlay {
            animation: fadeOutInfo 5s ease-in-out forwards;
        }

        @keyframes fadeOutInfo {
            0% { opacity: 1; }
            70% { opacity: 1; }
            100% { opacity: 0; visibility: hidden; }
        }

        .video-container:hover .video-info-overlay {
            animation: none;
            opacity: 1;
            visibility: visible;
        }

        .video-controls-hint {
            position: absolute;
            bottom: 100px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0,0,0,0.8);
            backdrop-filter: blur(8px);
            color: white;
            padding: 0.75rem 1.25rem;
            border-radius: var(--radius-lg);
            z-index: 10;
            font-size: 0.85rem;
            text-align: center;
            white-space: nowrap;
            opacity: 0.9;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .rewind-hint {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0,0,0,0.8);
            backdrop-filter: blur(8px);
            color: white;
            padding: 1rem 2rem;
            border-radius: var(--radius-xl);
            font-size: 1.3rem;
            font-weight: bold;
            z-index: 20;
            pointer-events: none;
            animation: fadeOut 1.5s ease-in-out forwards;
            border: 1px solid rgba(255,255,255,0.1);
        }

        @keyframes fadeOut {
            0% { opacity: 1; }
            70% { opacity: 1; }
            100% { opacity: 0; }
        }

        .quality-selector {
            position: absolute;
            top: 80px;
            right: 80px;
            padding: 0.5rem 1rem;
            border-radius: var(--radius-md);
            background: rgba(0,0,0,0.8);
            backdrop-filter: blur(8px);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            cursor: pointer;
            font-size: 0.9rem;
            z-index: 12;
            transition: all 0.3s ease;
        }

        .quality-selector:hover {
            border-color: var(--primary-color);
            transform: translateY(-2px);
        }

        .quality-selector option {
            background: #1a1a2e;
            color: white;
        }

        /* Admin Section */
        .admin-section {
            margin: 2rem 0;
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            background: var(--glass-bg-light);
        }

        .admin-section h3 {
            margin-bottom: 1rem;
            font-size: 1.25rem;
        }

        .admin-tabs {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .admin-tab {
            padding: 0.75rem 1.25rem;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-weight: 600;
            background: var(--glass-highlight);
            color: var(--text-secondary);
            transition: all 0.3s ease;
            border: 1px solid var(--glass-border);
        }

        .admin-tab:hover {
            background: rgba(<?php echo implode(', ', $rgb); ?>, 0.1);
            color: var(--text-primary);
        }

        .admin-tab.active {
            background: rgba(<?php echo implode(', ', $rgb); ?>, 0.2);
            color: var(--primary-light);
            border-color: var(--primary-color);
        }

        .admin-panel { display: none; }
        .admin-panel.show { display: block; }

        .access-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 700;
            background: rgba(<?php echo implode(', ', $rgb); ?>, 0.2);
            color: var(--primary-light);
            margin-left: 0.5rem;
            border: 1px solid rgba(<?php echo implode(', ', $rgb); ?>, 0.3);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header-content { padding: 0 1rem; }
            .container { padding: 0 1rem; }
            .search-sort-container { flex-direction: row; flex-wrap: nowrap; align-items: center; gap: 0.75rem; margin: 1rem 0; }
            .search-box { flex: 1; min-width: 0; max-width: none; }
            .movies-grid { grid-template-columns: 1fr; gap: 1rem; padding: 1rem 2%; }
            .movie-info { flex-direction: column; gap: 0.5rem; align-items: flex-start; }
            .movie-actions { flex-direction: row; gap: 0.5rem; }
            .watch-btn { flex: 1; }
            .modal-content { width: 95%; padding: 1.25rem; }
            .settings-table { font-size: 0.85rem; }
            .settings-table th, .settings-table td { padding: 0.5rem; }
            .video-player-modal .modal-content { width: 100%; height: 100%; }
            .comments-section { padding: 0.75rem; }
            .comment-form { padding: 0.75rem; }
            .quality-selector { top: 70px; right: 70px; font-size: 0.8rem; }
            .menu-toggle.floating { top: 0.5%; right: 2%; width: 46px; height: 46px; }
        }

        @media (max-width: 480px) {
            .movie-actions { flex-wrap: wrap; }
            .watch-btn { min-width: 100%; order: 1; }
            .comments-btn { order: 2; flex: 1; }
            .copy-btn { order: 3; }
            .quality-selector { top: 60px; right: 60px; padding: 0.4rem 0.75rem; }
            .app-title { font-size: 1.25rem; }
            .movies-grid { padding: 0.5rem 2%; }
            .menu-toggle.floating { top: 0.5%; right: 2%; width: 44px; height: 44px; }
        }

        .video-player-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: #000;
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            overflow: hidden;
        }

        .video-player-modal.show { display: flex; }

        .video-player-modal .modal-content {
            padding: 0;
            background: transparent;
            width: 100%;
            height: 100%;
            max-width: none;
            max-height: none;
            position: relative;
            border-radius: 0;
            display: flex;
            flex-direction: column;
        }

        .video-container {
            flex: 1;
            width: 100%;
            height: 100%;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .video-container video {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .plyr__controls {
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }

        @media (max-width: 768px) {
            .video-container { cursor: pointer; }
            .plyr--fullscreen-fallback .plyr__controls { padding: 10px; }
            .plyr__controls { padding: 10px; }
            .plyr__control { min-width: 40px; min-height: 40px; padding: 8px; }
            .plyr__time { font-size: 0.85rem; padding: 0 6px; }
        }

        @media (max-width: 480px) {
            .plyr__controls { padding: 8px; }
            .plyr__control { min-width: 36px; min-height: 36px; padding: 6px; }
            .plyr__time { display: none; }
            .video-info-overlay { top: 60px; padding: 0.5rem 1rem; font-size: 0.85rem; max-width: 95%; }
        }
    </style>
    <?php require_once dirname(__DIR__, 3) . '/lib/sentry_bootstrap.php'; app_sentry_browser_script('films'); ?>
</head>
