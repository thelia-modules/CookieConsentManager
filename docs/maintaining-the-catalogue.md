# Maintaining the service catalogue

This guide is for the maintainer of the module. With it you can update the catalogue of services from a new release of tarteaucitron.js and check the result before a release.

`Resources/catalogue/` holds the services that the module can load: `catalogue.json` (metadata), `fragments/<key>.js` (one upstream fragment per service) and `REPORT.md` (what the last import found).
It comes from the file `tarteaucitron.services.js` of [tarteaucitron.js](https://github.com/AmauriC/tarteaucitron.js) (MIT). The reasons are in [decision 001](decisions/001-service-catalogue.md).
The import is a maintainer task that runs on a developer machine, never on a shop. Updating the catalogue means releasing a new module version.

Files in `tools/catalogue/`:

| File | Role |
|------|------|
| `build.mjs` | entry point: vendors the upstream file, splits it, reads metadata, writes the catalogue, the fragments and `REPORT.md` |
| `split.mjs` | cuts the file on root assignments `tarteaucitron.services.<key> = {` and rejects duplicate keys, including keys that differ only by case |
| `sandbox.mjs` | evaluates each fragment's metadata in a `vm` context with simulated `tarteaucitron.user` values, reads the parameters, and detects services to exclude (`makeAsync`, `eval`, `document.write`, Consent Mode entries) |
| `executable.mjs` | derives which parameters are executable (their value decides the origin of a loaded script, inline code or markup), by running each fragment in jsdom with a marker as the value of every parameter and by reading the source; `build.mjs` writes the answer as `executable` on each parameter of `catalogue.json` |
| `purposes.mjs` | maps upstream types to module purposes, the per-service purpose of the upstream type `other`, the Google tag keys, and the services excluded by hand (`EXCLUDED_BY_KEY`) |
| `verified.json` | the hand-written table of verified services: name, purpose, kind, parameters with labels and patterns, cookies, storage keys, domains |
| `tarteaucitron-section3-services.json` | the listing of the services (`key`, `user_params`, `needs_html_markup`, `snippet`) that `build.mjs` reads by default, see step 2 below |
| `report.mjs` | renders `REPORT.md` |
| `embed-rules.mjs` | derives `Resources/catalogue/embed-rules.json`, the iframe hosts and path prefixes of the media services, from the vendored fragments. `--check` fails when the file is out of date |

`cookie-durations.json`, `controllers.json` and `joint-responsibility.json` in `Resources/catalogue/` are not generated.
They are edited by hand, from the official documentation of each publisher only. A value without an official source stays absent (see `REPORT.md` for the sources).

## Updating from a new tarteaucitron.js release

Requirements: Node 18 or later, and `npm install` once (the derivation of the executable parameters runs the fragments in jsdom). The script needs no network access.

1. Download `tarteaucitron.services.js` from the release tag you want (for example `v1.38.0`) and keep it outside the repository.
2. Run the import:

   ```bash
   node tools/catalogue/build.mjs --source /path/to/tarteaucitron.services.js --version 1.38.0 --expected <count>
   ```

   Every option takes a value. `--source` is required. `--version` (default `1.37.0`) is written into the vendored header and into `REPORT.md`.
   `--expected` (default `249`, the number of root assignments in v1.37.0) is the count the script checks. It stops when the count differs, so that a changed file is never imported unnoticed. Look at the upstream diff, then pass the new count.
   `npm run catalogue:build -- --source ...` does the same.
   `--section3 <json>` replaces the default listing `tools/catalogue/tarteaucitron-section3-services.json`. The listing cross-checks parameters and fills the `markup` field (the HTML a merchant pastes for services that work on markup). If the listing file is missing, `needsMarkup` is derived from the code and `markup` is left empty, which empties the field for the services that have one today. Restore those fields from the previous `catalogue.json` before committing.
3. Fix what the script refuses. It fails, with the service name, in these cases:
   - an upstream service of type `other` that has no entry in `OTHER_PURPOSE` (`purposes.mjs`): add its purpose;
   - an unknown upstream type: extend `TYPE_TO_PURPOSE`;
   - a service without `js()`;
   - a verified service whose parameters in `verified.json` differ from the ones read in the fragment, or that became excluded: update `verified.json`;
   - a verified key that no longer exists upstream.
4. Read the git diff of `Resources/catalogue/` and of `Resources/vendor/`. Fragments run on visitors' browsers: read each changed fragment of a verified service, and check the new cookies and domains against the publisher's documentation. Update `verified.json`, `cookie-durations.json` and `controllers.json` where needed.
5. Run `node tools/catalogue/embed-rules.mjs` to regenerate the iframe rules, and read the diff of `Resources/catalogue/embed-rules.json`. The list of services that are not rewritten in the [integrator guide](integrator-guide.md#hard-coded-scripts) must match its `undetectable` entries (a PHP test checks it).
6. Read `REPORT.md`, and in it the list of executable parameters: changing one of these needs the publish right (CREATE) in the back-office, on every screen, and the diff of `catalogue.json` shows which flags a new upstream version changed. The flag is generated: never edit it by hand. `tests/js/tools/executable.test.mjs` fails when a fragment loads a parameter that `catalogue.json` does not flag. Then read the rest of `REPORT.md`: excluded services, services whose upstream `needConsent` is `false` (ignored, every service requires consent), parameter differences, and the "Consistency" section.
7. Run the tests (`npm run test:js`, and the PHP suite from a Thelia site, see the [integrator guide](integrator-guide.md#tests)). The execution tests in `tests/js/compat/` run `js()` and `fallback()` of every exposed fragment against a compatibility layer that throws on any undeclared access. A new access outside the contract fails there: extend the layer in `assets/js/compat.js` or exclude the service.
8. Release a new module version. The served script changes, so each merchant must publish again. The dashboard shows the alert "rendering differs from the published version".

`build.mjs` also rewrites `Resources/vendor/tarteaucitron/tarteaucitron.services.js` (with a header that carries the sha256 of the upstream file) and `LICENSE`. The vendored file is excluded from the Composer archive (`.gitattributes`).
