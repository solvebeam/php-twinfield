// @ts-check
import { defineConfig } from 'astro/config';
import starlight from '@astrojs/starlight';

export default defineConfig({
	site: 'https://php-twinfield.solvebeam.com',
	integrations: [
		starlight({
			title: 'PHP Twinfield SDK',
			description: 'Modern PHP SDK for the Twinfield API, built on PSR standards.',
			logo: {
				src: './src/assets/logo.svg',
			},
			favicon: '/favicon.svg',
			social: [
				{ icon: 'github', label: 'GitHub', href: 'https://github.com/solvebeam/php-twinfield' },
			],
			editLink: {
				baseUrl: 'https://github.com/solvebeam/php-twinfield/edit/main/docs/',
			},
			lastUpdated: true,
			customCss: ['./src/styles/custom.css'],
			expressiveCode: {
				themes: ['github-dark-default', 'github-light-default'],
				styleOverrides: {
					borderRadius: '0.6rem',
				},
			},
			sidebar: [
				{
					label: 'Start here',
					items: [
						{ label: 'Installation', slug: 'getting-started/installation' },
						{ label: 'Running the examples', slug: 'examples' },
						{ label: 'OAuth2 and scopes', slug: 'guides/oauth2' },
						{ label: 'XML web services (ProcessXml)', slug: 'guides/process-xml' },
						{ label: 'Master data (dimensions)', slug: 'guides/master-data' },
					],
				},
				{
					label: 'Getting started',
					items: [{ autogenerate: { directory: 'examples/getting-started' } }],
				},
				{
					label: 'Offices',
					items: [{ autogenerate: { directory: 'examples/offices' } }],
				},
				{
					label: 'Activities',
					items: [{ autogenerate: { directory: 'examples/activities' } }],
				},
				{
					label: 'Finder',
					items: [{ autogenerate: { directory: 'examples/finder' } }],
				},
				{
					label: 'Periods',
					items: [{ autogenerate: { directory: 'examples/periods' } }],
				},
				{
					label: 'ProcessXml',
					items: [{ autogenerate: { directory: 'examples/process-xml' } }],
				},
				{
					label: 'Reference',
					items: [
						{ label: 'PSR standards', slug: 'reference/standards' },
						{ label: 'Development and testing', slug: 'reference/development' },
					],
				},
			],
		}),
	],
});
