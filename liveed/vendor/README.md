# Liveed vendored assets

This directory is the project-controlled location for third-party runtime assets used by `liveed/builder.php`.

Pinned versions:
- Font Awesome `6.4.2` → `fontawesome-6.4.2/`
- CropperJS `1.5.12` → `cropperjs-1.5.12/`
- jQuery `3.6.0` → `jquery-3.6.0/`

## Populate / refresh assets

Run:

```bash
bash liveed/vendor/fetch-vendor-assets.sh
```

The fetch script downloads minified files from CDNJS into this directory and prints Subresource Integrity hashes so they can be pinned in PHP fallback tags.
