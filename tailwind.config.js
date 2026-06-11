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
                    'purple-muted': '#875A7B',
                    'purple-light': '#C9B8C5',
                    teal: '#17A2B8',
                    'login-info-bg': '#E8F6F8',
                    'login-info-border': '#B8DEE4',
                    'login-info-text': '#016878',
                    nav: '#875A7B',
                    sidebar: '#F8F9FA',
                    border: '#DEE2E6',
                    link: '#007BFF',
                    'login-page': '#EDEDED',
                    'logo-gray': '#8F8F8F',
                },
            },
        },
    },
    plugins: [],
};
