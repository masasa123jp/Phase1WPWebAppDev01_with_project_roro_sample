import { __ } from '@wordpress/i18n';

/** Tiny wrapper for @wordpress/i18n */
export const useLocale = () => (key: string) => __(key, 'roro-core');
