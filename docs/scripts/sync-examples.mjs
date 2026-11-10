// Copies examples/**/README.md into the docs, so the examples stay the single source.
//
// examples/README.md                 → src/content/docs/examples/index.md
// examples/finder/paginate/README.md → src/content/docs/examples/finder/paginate.md

import { existsSync, readdirSync } from 'node:fs';
import { mkdir, readdir, readFile, rm, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const docsDir = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const repoDir = path.resolve(docsDir, '..');
const examplesDir = path.join(repoDir, 'examples');
const outDir = path.join(docsDir, 'src/content/docs/examples');

const repoUrl = 'https://github.com/solvebeam/twinfield-php';

// Repository files that have their own page in the docs.
const pageForRepoFile = {
	'README.md#scopes': `/guides/oauth2/#scopes`,
	'README.md': `/`,
};

async function findReadmes(dir) {
	const found = [];

	for (const entry of await readdir(dir, { withFileTypes: true })) {
		const full = path.join(dir, entry.name);

		if (entry.isDirectory()) {
			found.push(...(await findReadmes(full)));
		} else if (entry.name === 'README.md') {
			found.push(full);
		}
	}

	return found;
}

/** Rewrites a relative link in a README so it works on the docs site. */
function rewriteLink(href, readmeDir) {
	if (/^[a-z]+:/i.test(href) || href.startsWith('#')) {
		return href;
	}

	const [target, hash = ''] = href.split('#');
	const resolved = path.relative(repoDir, path.resolve(readmeDir, target)).split(path.sep).join('/');
	const withHash = hash ? `${resolved}#${hash}` : resolved;

	if (pageForRepoFile[withHash]) {
		return pageForRepoFile[withHash];
	}

	if (pageForRepoFile[resolved]) {
		return pageForRepoFile[resolved] + (hash ? `#${hash}` : '');
	}

	const isExamplePage = resolved.startsWith('examples') && existsSync(path.join(repoDir, resolved, 'README.md'));

	if (isExamplePage) {
		return `/${resolved}/` + (hash ? `#${hash}` : '');
	}

	return `${repoUrl}/blob/main/${withHash}`;
}

function toPage(markdown, readmePath, order) {
	const readmeDir = path.dirname(readmePath);
	const relativeReadme = path.relative(repoDir, readmePath).split(path.sep).join('/');

	const titleMatch = markdown.match(/^# (.+)\n+/m);

	if (!titleMatch) {
		throw new Error(`${relativeReadme} has no "# Title" heading.`);
	}

	const title = titleMatch[1].trim();
	let body = markdown.replace(titleMatch[0], '');

	const firstParagraph = body.split(/\n\s*\n/)[0];
	const description = firstParagraph
		.replace(/\[([^\]]+)\]\([^)]+\)/g, '$1')
		.replace(/`/g, '')
		.replace(/\s+/g, ' ')
		.trim();

	// Leave fenced code blocks alone, only rewrite links in the prose.
	body = body
		.split(/(```[\s\S]*?```)/)
		.map((part, index) =>
			index % 2 === 1 ? part : part.replace(/\]\(([^)\s]+)\)/g, (match, href) => `](${rewriteLink(href, readmeDir)})`)
		)
		.join('');

	// Exact name check: the file system may be case-insensitive (examples/Example.php is the helper).
	if (readdirSync(readmeDir).includes('example.php')) {
		const examplePath = path.relative(repoDir, path.join(readmeDir, 'example.php')).split(path.sep).join('/');

		body = `${body.trimEnd()}\n\n## Source\n\nView [\`${examplePath}\`](${repoUrl}/blob/main/${examplePath}) on GitHub.\n`;
	}

	const frontmatter = [
		'---',
		`title: ${JSON.stringify(title)}`,
		description ? `description: ${JSON.stringify(description)}` : null,
		`editUrl: ${JSON.stringify(`${repoUrl}/edit/main/${relativeReadme}`)}`,
		order === undefined ? null : `sidebar:\n  order: ${order}`,
		'---',
		'',
		`<!-- Generated from ${relativeReadme} by docs/scripts/sync-examples.mjs, do not edit. -->`,
		'',
	]
		.filter((line) => line !== null)
		.join('\n');

	return frontmatter + body;
}

const indexReadme = path.join(examplesDir, 'README.md');
const indexMarkdown = await readFile(indexReadme, 'utf8');

// The order of the list items in examples/README.md is the order in the sidebar.
const order = [...indexMarkdown.matchAll(/^- \[[^\]]+\]\(([^)#]+)\/\)/gm)].map((match) => match[1]);

await rm(outDir, { recursive: true, force: true });

const readmes = (await findReadmes(examplesDir)).sort();

for (const readme of readmes) {
	const relativeDir = path.relative(examplesDir, path.dirname(readme)).split(path.sep).join('/');
	const target = relativeDir === '' ? path.join(outDir, 'index.md') : path.join(outDir, `${relativeDir}.md`);
	const position = order.indexOf(relativeDir);

	await mkdir(path.dirname(target), { recursive: true });
	await writeFile(target, toPage(await readFile(readme, 'utf8'), readme, position === -1 ? undefined : position));

	console.log(`${path.relative(repoDir, readme)} → ${path.relative(docsDir, target)}`);
}

console.log(`Synced ${readmes.length} example pages.`);
