import { PHP } from '@php-wasm/universal';
import { loadNodeRuntime, useHostFilesystem } from '@php-wasm/node';
const php = new PHP(await loadNodeRuntime('8.3', { emscriptenOptions: { processId: 14 } }));
useHostFilesystem(php);
const out = await php.runStream({ code: `<?php
foreach (['includes/publishers.php','includes/maker.php'] as $f) {
  $src = file_get_contents('/home/user/autoblog-Local/backlink-maker/' . $f);
  try { token_get_all($src, TOKEN_PARSE); echo "OK   $f\\n"; }
  catch (Throwable $e) { echo "FAIL $f -> " . $e->getMessage() . "\\n"; }
}
require '/home/user/autoblog-Local/backlink-maker/includes/maker.php';
echo 'classes: ' . implode(',', array_filter(['BacklinkPublisher','BacklinkMaker','LinkVerifier','AIProviderClient'], 'class_exists')) . "\\n";
`});
console.log(await out.stdoutText);
const e = await out.stderrText;
if (e) console.log('STDERR:', e.slice(0, 300));
process.exit(0);
