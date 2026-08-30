import { createHash } from 'node:crypto';
import {
  access,
  cp,
  mkdir,
  mkdtemp,
  readFile,
  readdir,
  rename,
  rm,
} from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const mode = process.argv[2] ?? '--check';

if (!['--check', '--write'].includes(mode)) {
  throw new Error('Usage: node scripts/sync-dist.mjs [--check|--write]');
}

const scriptsDirectory = path.dirname(fileURLToPath(import.meta.url));
const projectRoot = path.resolve(scriptsDirectory, '..');
const sourceDirectory = path.join(scriptsDirectory, 'dist');
const targetDirectory = path.join(projectRoot, 'js');
const requiredFiles = [
  'app.js',
  'nav.js',
  'page.js',
  'polyfill.js',
  'smoothscroll.js',
  'theme-color-worker.js',
];

function assertSafePaths() {
  if (
    path.basename(targetDirectory) !== 'js' ||
    path.dirname(targetDirectory) !== projectRoot ||
    path.dirname(sourceDirectory) !== scriptsDirectory
  ) {
    throw new Error('Refusing to sync assets because the resolved paths are unsafe.');
  }
}

async function assertProjectLayout() {
  assertSafePaths();
  await Promise.all([
    access(path.join(projectRoot, 'style.css')),
    access(path.join(projectRoot, 'functions.php')),
    access(sourceDirectory),
    access(targetDirectory),
    ...requiredFiles.map((file) => access(path.join(sourceDirectory, file))),
  ]);
}

async function listFiles(directory, prefix = '') {
  const entries = await readdir(directory, { withFileTypes: true });
  const files = [];

  for (const entry of entries) {
    const relativePath = path.join(prefix, entry.name);
    const absolutePath = path.join(directory, entry.name);

    if (entry.isDirectory()) {
      files.push(...(await listFiles(absolutePath, relativePath)));
    } else if (entry.isFile()) {
      files.push(relativePath.split(path.sep).join('/'));
    }
  }

  return files.sort();
}

async function hashFile(file) {
  const contents = await readFile(file);
  return createHash('sha256').update(contents).digest('hex');
}

async function compareDirectories() {
  const sourceFiles = await listFiles(sourceDirectory);
  const targetFiles = await listFiles(targetDirectory);
  const sourceSet = new Set(sourceFiles);
  const targetSet = new Set(targetFiles);
  const added = sourceFiles.filter((file) => !targetSet.has(file));
  const removed = targetFiles.filter((file) => !sourceSet.has(file));
  const changed = [];

  for (const file of sourceFiles.filter((candidate) => targetSet.has(candidate))) {
    const [sourceHash, targetHash] = await Promise.all([
      hashFile(path.join(sourceDirectory, file)),
      hashFile(path.join(targetDirectory, file)),
    ]);

    if (sourceHash !== targetHash) {
      changed.push(file);
    }
  }

  return { added, changed, removed, sourceCount: sourceFiles.length };
}

function printComparison(result) {
  console.log(`Build contains ${result.sourceCount} files.`);
  console.log(
    `Compared with js/: ${result.added.length} added, ${result.changed.length} changed, ${result.removed.length} removed.`,
  );

  for (const [label, files] of [
    ['Added', result.added],
    ['Changed', result.changed],
    ['Removed', result.removed],
  ]) {
    if (files.length > 0) {
      console.log(`\n${label}:`);
      files.forEach((file) => console.log(`  ${file}`));
    }
  }
}

async function replaceTargetDirectory() {
  const stagingRoot = await mkdtemp(path.join(projectRoot, '.js-sync-'));
  const stagedDirectory = path.join(stagingRoot, 'js');
  const backupDirectory = path.join(projectRoot, `js.backup-${process.pid}`);
  let targetWasMoved = false;

  try {
    await mkdir(stagedDirectory);
    await cp(sourceDirectory, stagedDirectory, { recursive: true });
    await rename(targetDirectory, backupDirectory);
    targetWasMoved = true;
    await rename(stagedDirectory, targetDirectory);
    targetWasMoved = false;
    await rm(backupDirectory, { force: true, recursive: true });
  } catch (error) {
    if (targetWasMoved) {
      await rename(backupDirectory, targetDirectory);
    }
    throw error;
  } finally {
    await rm(stagingRoot, { force: true, recursive: true });
  }
}

await assertProjectLayout();
const comparison = await compareDirectories();
printComparison(comparison);

const hasChanges =
  comparison.added.length > 0 ||
  comparison.changed.length > 0 ||
  comparison.removed.length > 0;

if (mode === '--check') {
  if (hasChanges) {
    process.exitCode = 1;
  } else {
    console.log('\nThe compiled assets are already synchronized.');
  }
} else if (!hasChanges) {
  console.log('\nNothing to deploy.');
} else {
  await replaceTargetDirectory();
  console.log('\nThe js/ directory was replaced atomically with the validated build output.');
}
