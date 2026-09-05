import { PHP } from '@php-wasm/universal';
import { loadNodeRuntime, useHostFilesystem } from '@php-wasm/node';
const php = new PHP(await loadNodeRuntime('8.3', { emscriptenOptions: { processId: 13 } }));
useHostFilesystem(php);
const out = await php.runStream({ code: `<?php
require '/home/user/autoblog-Local/backlink-maker/includes/maker.php';
echo 'classes ok\n';
echo 'sandbox: ' . (defined('SANDBOX_MODE') ? SANDBOX_MODE : '?') . "\n";
`});
console.log(await out.stdoutText);
const e = await out.stderrText;
if (e) console.log('STDERR:', e.slice(0, 400));
process.exit(0);
