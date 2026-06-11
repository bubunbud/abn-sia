/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
    ],
    theme: {
        extend: {
            colors: {
                odoo: {
                    purple: '#714B67',
                    'purple-dark': '#5B3D54',
                    teal: '#17A2B8',
                    nav: '#875A7B',
                    sidebar: '#F8F9FA',
                    border: '#DEE2E6',
                    link: '#007BFF',
                },
            },
        },
    },
    plugins: [],
};
