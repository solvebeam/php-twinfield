// @ts-check
import { defineConfig } from 'astro/config';
import starlight from '@astrojs/starlight';
import mermaid from 'astro-mermaid';

export default defineConfig({
	site: 'https://php-twinfield.solvebeam.com',
	integrations: [
		// Before Starlight, so ```mermaid code blocks are rendered as diagrams instead of code.
		mermaid({ theme: 'default', autoTheme: true }),
		starlight({
			title: 'PHP Twinfield SDK',
			description: 'Modern PHP SDK for the Twinfield API, built on PSR standards.',
			logo: {
				src: './src/assets/brand/twinfield/twinfield-icon.svg',
				alt: 'Twinfield',
			},
			favicon: '/favicon.svg',
			editLink: {
				baseUrl: 'https://github.com/solvebeam/php-twinfield/edit/main/docs/',
			},
			lastUpdated: true,
			customCss: ['./src/styles/custom.css'],
			components: {
				Footer: './src/components/Footer.astro',
				SocialIcons: './src/components/SocialIcons.astro',
			},
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
						{ label: 'Organisations and companies', slug: 'guides/organisations' },
						{ label: 'XML web services (ProcessXml)', slug: 'guides/process-xml' },
						{ label: 'Dimensions', slug: 'guides/dimensions' },
						{ label: 'Master data (dimensions)', slug: 'guides/master-data' },
						{ label: 'Finder', slug: 'guides/finder' },
						{ label: 'Transactions', slug: 'guides/transactions' },
						{ label: 'Browse data', slug: 'guides/browse-data' },
						{ label: 'Declarations', slug: 'guides/declarations' },
						{ label: 'Reporting structures', slug: 'guides/hierarchies' },
						{ label: 'Rate limits', slug: 'guides/rate-limits' },
						{ label: 'Differences with the documentation', slug: 'guides/documentation-differences' },
					],
				},
				{
					label: 'Getting started',
					items: [{ autogenerate: { directory: 'examples/getting-started' } }],
				},
				// The groups below follow the API reference on developers.twinfield.com/documentation, with the same names and order.
				{
					label: 'XML Web Services',
					items: [
						{ label: 'ProcessXml', collapsed: true, items: [{ autogenerate: { directory: 'examples/process-xml' } }] },
					],
				},
				{
					label: 'Master Data',
					items: [
						{ label: 'Activities', collapsed: true, items: [{ autogenerate: { directory: 'examples/activities' } }] },
						{ label: 'Asset methods', collapsed: true, items: [{ autogenerate: { directory: 'examples/asset-methods' } }] },
						{ label: 'Bank book', collapsed: true, items: [{ autogenerate: { directory: 'examples/bank-books' } }] },
						{ label: 'Budget', collapsed: true, items: [{ autogenerate: { directory: 'examples/budgets' } }] },
						{ label: 'Cash book', collapsed: true, items: [{ autogenerate: { directory: 'examples/cash-books' } }] },
						{ label: 'Cost centers', collapsed: true, items: [{ autogenerate: { directory: 'examples/cost-centers' } }] },
						{ label: 'Currencies', collapsed: true, items: [{ autogenerate: { directory: 'examples/currencies' } }] },
						{ label: 'Customers', collapsed: true, items: [{ autogenerate: { directory: 'examples/customers' } }] },
						{ label: 'Dimension groups', collapsed: true, items: [{ autogenerate: { directory: 'examples/dimension-groups' } }] },
						{ label: 'Dimension types', collapsed: true, items: [{ autogenerate: { directory: 'examples/dimension-types' } }] },
						{ label: 'Fixed assets', collapsed: true, items: [{ autogenerate: { directory: 'examples/fixed-assets' } }] },
						{ label: 'General ledger accounts', collapsed: true, items: [{ autogenerate: { directory: 'examples/general-ledger-accounts' } }] },
						{ label: 'Offices', collapsed: true, items: [{ autogenerate: { directory: 'examples/offices' } }] },
						{ label: 'Projects', collapsed: true, items: [{ autogenerate: { directory: 'examples/projects' } }] },
						{ label: 'Rates', collapsed: true, items: [{ autogenerate: { directory: 'examples/rates' } }] },
						{ label: 'Suppliers', collapsed: true, items: [{ autogenerate: { directory: 'examples/suppliers' } }] },
						{ label: 'Users', collapsed: true, items: [{ autogenerate: { directory: 'examples/users' } }] },
						{ label: 'VAT', collapsed: true, items: [{ autogenerate: { directory: 'examples/vat-codes' } }] },
					],
				},
				{
					label: 'Transaction Data',
					items: [
						{ label: 'Bank transactions', collapsed: true, items: [{ autogenerate: { directory: 'examples/bank-transactions' } }] },
						{ label: 'Cash transactions', collapsed: true, items: [{ autogenerate: { directory: 'examples/cash-transactions' } }] },
						{ label: 'Deleted transactions', collapsed: true, items: [{ autogenerate: { directory: 'examples/deleted-transactions' } }] },
						{ label: 'Electronic bank statements', collapsed: true, items: [{ autogenerate: { directory: 'examples/electronic-bank-statements' } }] },
						{ label: 'Journal transactions', collapsed: true, items: [{ autogenerate: { directory: 'examples/journal-transactions' } }] },
						{ label: 'Purchase transactions', collapsed: true, items: [{ autogenerate: { directory: 'examples/purchase-transactions' } }] },
						{ label: 'Reversal transactions', collapsed: true, items: [{ autogenerate: { directory: 'examples/reversal-transactions' } }] },
						{ label: 'Sales invoices', collapsed: true, items: [{ autogenerate: { directory: 'examples/sales-invoices' } }] },
						{ label: 'Sales transactions', collapsed: true, items: [{ autogenerate: { directory: 'examples/sales-transactions' } }] },
						{ label: 'Transaction blocked value', collapsed: true, items: [{ autogenerate: { directory: 'examples/transaction-blocked-values' } }] },
					],
				},
				{
					label: 'Request Data',
					items: [
						{ label: 'Browse data', collapsed: true, items: [{ autogenerate: { directory: 'examples/browse-data' } }] },
						{ label: 'Shadow database', collapsed: true, items: [{ autogenerate: { directory: 'examples/shadow-database' } }] },
					],
				},
				{
					label: 'Miscellaneous',
					items: [
						{ label: 'Declarations', collapsed: true, items: [{ autogenerate: { directory: 'examples/declarations' } }] },
						{ label: 'Reporting structures', collapsed: true, items: [{ autogenerate: { directory: 'examples/hierarchies' } }] },
						{ label: 'Finder', collapsed: true, items: [{ autogenerate: { directory: 'examples/finder' } }] },
						{ label: 'Periods', collapsed: true, items: [{ autogenerate: { directory: 'examples/periods' } }] },
					],
				},
				{
					label: 'Twinfield knowledge base',
					items: [{ autogenerate: { directory: 'knowledge-base' } }],
				},
				{
					label: 'Reference',
					items: [
						{ label: 'PSR standards', slug: 'reference/standards' },
						{ label: 'Development and testing', slug: 'reference/development' },
						{ label: 'Twinfield web services manuals', slug: 'reference/manuals' },
					],
				},
			],
		}),
	],
});
