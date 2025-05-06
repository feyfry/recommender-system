/** @type {import('tailwindcss').Config} */
export default {
    content: [
        "./resources/**/*.blade.php",
        "./resources/**/*.js",
        "./resources/**/*.vue",
    ],
    theme: {
        extend: {
            colors: {
                primary: '#6366f1',
                secondary: '#ec4899',
                success: '#10b981',
                warning: '#f59e0b',
                danger: '#ef4444',
                info: '#3b82f6',
            },
            fontFamily: {
                sans: ['Inter', 'sans-serif'],
            },
            animation: {
                spin: 'spin 1s linear infinite',
            },
            keyframes: {
                spin: {
                    'from': {
                        transform: 'rotate(0deg)'
                    },
                    'to': {
                        transform: 'rotate(360deg)'
                    },
                }
            }
        },
    },
    plugins: [],
}
