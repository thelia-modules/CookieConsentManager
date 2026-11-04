# Maintaining the service catalogue

`Resources/catalogue/` holds the services that the module can load: `catalogue.json` (metadata), `fragments/<key>.js` (one upstream fragment per service) and `REPORT.md` (what the last import found). It comes from the file `tarteaucitron.services.js` of [tarteaucitron.js](https://github.com/AmauriC/tarteaucitron.js) (MIT). The reasons are in [decision 001](decisions/001-service-catalogue.md). The import is a maintainer task that runs on a developer machine, never on a shop. Updating the catalogue means releasing a new module version.

Files in `tools/catalogue/`:

| File | Role |
|------|------|
| `build.mjs` | entry point: vendors the upstream file, splits it, reads metadata, writes the catalogue, the fragments and `REPORT.md` |
| `split.mjs` | cuts the file on root assignments `tarteaucitron.services.<key> = {` and rejects duplicate keys, including keys that differ only by case |
| `sandbox.mjs` | evaluates each fragment's metadata in a `vm` context with simulated `tarteaucitron.user` values, reads the parameters, and detects services to exclude (`makeAsync`, `eval`, `document.write`, Google Consent Mode entries) |
| `purposes.mjs` | maps upstream types to module purposes, the per-service purpose of the upstream type `other`, the Google tag keys, and the services excluded by hand (`EXCLUDED_BY_KEY`) |
| `verified.json` | the hand-written table of verified services: name, purpose, kind, parameters with labels and patterns, cookies, storage keys, domains |
| `report.mjs` | renders `REPORT.md` |

`cookie-durations.json`, `controllers.json` and `joint-responsibility.json` in `Resources/catalogue/` are not generated. They are edited by hand, from the official documentation of each publisher only; a value without an official source stays absent (see `REPORT.md` for the sources).

## Updating from a new tarteaucitron.js release

Requirements: Node 18 or later, no network access needed by the script.

1. Download `tarteaucitron.services.js` from the release tag you want (for example `v1.38.0`) and keep it outside the repository.
2. Run the import:

   ```bash
   node tools/catalogue/build.mjs --source /path/to/tarteaucitron.services.js --version 1.38.0 --expected <count>
   ```

   `--expected` is the number of root assignments in the file (249 for v1.37.0). The script stops when the count differs, so that a changed file is never imported unnoticed: look at the upstream diff, then pass the new count. `npm run catalogue:build -- --source ...` does the same.
   `--section3 <json>` is optional. It takes a JSON listing of the services with `key`, `user_params`, `needs_html_markup` and `snippet`, used to cross-check parameters and to fill the `markup` field (the HTML a merchant pastes for services that work on markup). Without it, `needsMarkup` is derived from the code and `markup` is left empty, which empties the field for the services that have one today. Run the import with the listing when you have it, or restore those fields from the previous `catalogue.json` before committing.
3. Fix what the script refuses. It fails, with the service name, in these cases:
   - an upstream service of type `other` that has no entry in `OTHER_PURPOSE` (`purposes.mjs`): add its purpose;
   - an unknown upstream type: extend `TYPE_TO_PURPOSE`;
   - a service without `js()`;
   - a verified service whose parameters in `verified.json` differ from the ones read in the fragment, or that became excluded: update `verified.json`;
   - a verified key that no longer exists upstream.
4. Read the git diff of `Resources/catalogue/` and of `Resources/vendor/`. Fragments run on visitors' browsers: read each changed fragment of a verified service, and check the new cookies and domains against the publisher's documentation. Update `verified.json`, `cookie-durations.json` and `controllers.json` where needed.
5. Read `REPORT.md`: excluded services, services whose upstream `needConsent` is `false` (ignored, every service requires consent), parameter differences, and the "Consistency" section.
6. Run the tests (`npm run test:js`, and the PHP suite from a Thelia site, see the [integrator guide](integrator-guide.md#tests)). The execution tests in `tests/js/compat/` run `js()` and `fallback()` of every exposed fragment against a compatibility layer that throws on any undeclared access. A new access outside the contract fails there: extend the layer in `assets/js/compat.js` or exclude the service.
7. Release a new module version. The served script changes, so each merchant must publish again; the dashboard shows the alert "rendering differs from the published version".

`build.mjs` also rewrites `Resources/vendor/tarteaucitron/tarteaucitron.services.js` (with a header that carries the sha256 of the upstream file) and `LICENSE`. That vendored file is excluded from the Composer archive (`.gitattributes`).
