import { defineConfig } from 'vitepress'

// https://vitepress.dev/reference/site-config
export default defineConfig({
  title: 'CakePHP Sentry',
  description: 'Documentation for the CakePHP Sentry plugin',

  base: '/cakephp-sentry/', // Should be the same as the repo name

  head: [
    ['meta', { name: 'theme-color', content: '#D33C43' }],
    ['meta', { property: 'og:type', content: 'website' }],
    ['meta', { property: 'og:title', content: 'CakePHP Sentry Plugin' }],
    ['meta', { property: 'og:description', content: 'A CakePHP Plugin for Sentry Integration' }],
  ],

  themeConfig: {
    nav: [
      { text: 'Home', link: '/' },
      { text: 'Getting Started', link: '/guide/getting-started' },
      { text: 'GitHub', link: 'https://github.com/lordsimal/cakephp-sentry' },
    ],

    sidebar: [
      {
        text: 'Documentation',
        items: [
          { text: 'Getting Started', link: '/guide/getting-started' },
          { text: 'Configuration', link: '/guide/configuration' },
          { text: 'Event Hooks', link: '/guide/event-hooks' },
          { text: 'Query Logging', link: '/guide/query-logging' },
          { text: 'Performance Monitoring', link: '/guide/performance-monitoring' },
          { text: 'Logging', link: '/guide/logging' },
          { text: 'Queue Integration', link: '/guide/queue-integration' },
          { text: 'Changelog', link: '/guide/changelog' },
        ],
      },
    ],

    socialLinks: [
      { icon: 'github', link: 'https://github.com/lordsimal/cakephp-sentry' },
    ],

    search: {
      provider: 'local',
    },
  },
})
