#!/bin/bash
set -e
# create bundle.js
cat <<'EOS' > bundle.js
// bundle.js - combined modules from src
(function() {
EOS
# assets_data.js without export default
sed '/export default/d' src/assets_data.js >> bundle.js

# assets.js: remove import and export
sed -e "s/^import .*//" -e "s/^export //" src/assets.js >> bundle.js

# data.js: remove export
sed -e "s/^export //" src/data.js >> bundle.js

# audio.js: remove export const
sed -e "s/^export const /const /" src/audio.js >> bundle.js

# world.js: remove import and export
sed -e "s/^import .*//" -e "s/^export function/function/" src/world.js >> bundle.js

# entities.js: remove import and export class
sed -e "s/^import .*//" -e "s/^export class/class/" src/entities.js >> bundle.js

# scenes.js: remove import and export (scenes does not export anything, but for safety)
sed -e "s/^import .*//" -e "s/^export //" src/scenes.js >> bundle.js

# game.js: remove import and export class
sed -e "s/^import .*//" -e "s/^export class/class/" src/game.js >> bundle.js

# main.js: remove import statements
sed -e "s/^import .*//" src/main.js >> bundle.js

# Close IIFE
cat <<'EOS' >> bundle.js
})();
EOS
