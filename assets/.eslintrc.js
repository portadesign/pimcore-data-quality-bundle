module.exports = {
  root: true,
  env: {
    browser: true,
    es2021: true
  },
  extends: [
    'eslint-config-standard-with-typescript'
  ],
  parserOptions: {
    project: './tsconfig.json',
    ecmaFeatures: { jsx: true }
  },
  plugins: ['react', 'jsx-a11y'],
  settings: {
    react: { version: 'detect' }
  },
  rules: {
    '@typescript-eslint/no-unsafe-assignment': 'off',
    '@typescript-eslint/no-unsafe-member-access': 'off',
    '@typescript-eslint/no-unsafe-argument': 'off'
  }
}
