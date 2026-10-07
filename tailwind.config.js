import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['"Plus Jakarta Sans"', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                hl: {
                    navy: '#1A2B48',
                    muted: '#6B7C93',
                    blue: '#2F80ED',
                    'blue-soft': '#E8F1FF',
                    sky: '#4DA3FF',
                    orange: '#F97316',
                    green: '#22C55E',
                    purple: '#8B5CF6',
                    teal: '#14B8A6',
                },
            },
            boxShadow: {
                card: '0 18px 40px rgba(26, 43, 72, 0.07)',
                sidebar: '0 20px 50px rgba(26, 43, 72, 0.08)',
            },
            borderRadius: {
                '2.5xl': '1.25rem',
                '3xl': '1.5rem',
                '4xl': '1.75rem',
            },
        },
    },

    plugins: [forms],
};
