/**
 * Storybook プレビュー – i18n + Tailwind + Router デコレータ。
 */
import type { Preview } from '@storybook/react';
import '../src/styles/tailwind.css';
import { MemoryRouter } from 'react-router-dom';
import { withI18next } from 'storybook-addon-i18next';

const preview: Preview = {
  decorators: [
    withI18next({
      i18n: {
        defaultLocale: 'ja',
        locales: ['ja', 'en', 'zh-CN', 'ko'],
      },
    }),
    (Story) => (
      <MemoryRouter initialEntries={['/']}>
        <Story />
      </MemoryRouter>
    ),
  ],
};
export default preview;
