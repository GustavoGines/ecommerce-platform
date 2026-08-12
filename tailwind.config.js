import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                g3: {
                    dark: '#09090b',
                    card: '#18181b',
                    blue: '#007ACC',
                    green: '#7ED321',
                    silver: '#a1a1aa',
                }
            },
            backgroundImage: {
                'gradient-g3': 'linear-gradient(to right, #007ACC, #7ED321)',
            }
        },
    },
    plugins: [forms],
};
