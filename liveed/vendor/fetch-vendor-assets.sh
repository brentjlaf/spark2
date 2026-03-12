#!/usr/bin/env bash
set -euo pipefail

base_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

fetch() {
  local url="$1"
  local out="$2"
  mkdir -p "$(dirname "$out")"
  echo "Downloading $url -> $out"
  curl -fSL "$url" -o "$out"
}

sri() {
  local file="$1"
  local hash
  hash="$(openssl dgst -sha512 -binary "$file" | openssl base64 -A)"
  echo "sha512-$hash"
}

fetch "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" "$base_dir/fontawesome-6.4.2/css/all.min.css"
fetch "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/webfonts/fa-solid-900.woff2" "$base_dir/fontawesome-6.4.2/webfonts/fa-solid-900.woff2"
fetch "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/webfonts/fa-regular-400.woff2" "$base_dir/fontawesome-6.4.2/webfonts/fa-regular-400.woff2"
fetch "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/webfonts/fa-brands-400.woff2" "$base_dir/fontawesome-6.4.2/webfonts/fa-brands-400.woff2"
fetch "https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.css" "$base_dir/cropperjs-1.5.12/cropper.min.css"
fetch "https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.js" "$base_dir/cropperjs-1.5.12/cropper.min.js"
fetch "https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js" "$base_dir/jquery-3.6.0/jquery.min.js"

echo
echo "Computed SRI hashes (sha512):"
for f in \
  "$base_dir/fontawesome-6.4.2/css/all.min.css" \
  "$base_dir/cropperjs-1.5.12/cropper.min.css" \
  "$base_dir/cropperjs-1.5.12/cropper.min.js" \
  "$base_dir/jquery-3.6.0/jquery.min.js"
do
  echo "$(realpath --relative-to="$PWD" "$f"): $(sri "$f")"
done
