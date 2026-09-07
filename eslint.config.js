import { includeIgnoreFile } from '@eslint/compat';
import js from '@eslint/js';
import stylistic from '@stylistic/eslint-plugin';
import { defineConfigWithVueTs, vueTsConfigs } from '@vue/eslint-config-typescript';
import prettier from 'eslint-config-prettier/flat';
import importPlugin from 'eslint-plugin-import';
import vue from 'eslint-plugin-vue';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const gitignorePath = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '.gitignore');
const gitignore = includeIgnoreFile(gitignorePath);

const controlStatements = ['if', 'return', 'for', 'while', 'do', 'switch', 'try', 'throw'];
const paddingAroundControl = [
  ...controlStatements.flatMap((stmt) => [
    { blankLine: 'always', prev: '*', next: stmt },
    { blankLine: 'always', prev: stmt, next: '*' },
  ]),
];

export default defineConfigWithVueTs(
  {
    extends: [js.configs.recommended],
  },
  vue.configs['flat/essential'],
  vueTsConfigs.recommended,
  {
    plugins: {
      import: importPlugin,
    },
    settings: {
      'import/resolver': {
        typescript: {
          alwaysTryTypes: true,
          project: './tsconfig.json',
        },
        node: true,
      },
    },
    rules: {
      'vue/multi-word-component-names': 'off',
      '@typescript-eslint/no-explicit-any': 'off',
      '@typescript-eslint/consistent-type-imports': [
        'error',
        {
          prefer: 'type-imports',
          fixStyle: 'separate-type-imports',
        },
      ],
      'vue/component-name-in-template-casing': ['error', 'PascalCase'],
      'vue/attributes-order': [
        'error',
        {
          order: [
            'DEFINITION',
            'LIST_RENDERING',
            'CONDITIONALS',
            'RENDER_MODIFIERS',
            'GLOBAL',
            ['UNIQUE', 'SLOT'],
            'TWO_WAY_BINDING',
            'OTHER_DIRECTIVES',
            'OTHER_ATTR',
            'EVENTS',
            'CONTENT',
          ],
          alphabetical: false,
        },
      ],
      'vue/html-self-closing': [
        'error',
        {
          html: {
            void: 'always',
            normal: 'always',
            component: 'always',
          },
          svg: 'always',
          math: 'always',
        },
      ],
      'vue/v-bind-style': ['error', 'shorthand'],
      'vue/v-on-style': ['error', 'shorthand'],
      'vue/require-default-prop': 'error',
      'vue/html-indent': ['error', 2],
      '@typescript-eslint/no-unused-vars': [
        'error',
        {
          argsIgnorePattern: '^_',
          varsIgnorePattern: '^_',
        },
      ],
      'vue/prefer-import-from-vue': 'error',
    },
  },
  {
    plugins: {
      '@stylistic': stylistic,
    },
    rules: {
      '@stylistic/brace-style': ['error', '1tbs', { allowSingleLine: false }],
      '@stylistic/padding-line-between-statements': ['error', ...paddingAroundControl],
    },
  },
  {
    ignores: [
      ...gitignore.ignores,
      'vendor/**',
      'node_modules/**',
      'public/**',
      'bootstrap/ssr/**',
      'storage/**',
      '*.config.js',
      'vite.config.ts',
      'resources/js/actions/**',
      'resources/js/components/ui/*',
      'resources/js/routes/**',
      'resources/js/wayfinder/**',
    ],
  },
  prettier,
);
