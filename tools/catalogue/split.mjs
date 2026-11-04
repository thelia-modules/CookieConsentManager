const START = /^tarteaucitron\.services\.([A-Za-z0-9_]+) = \{\s*$/;
const END = /^\};?\s*$/;

/** Splits the upstream services file on root-level `tarteaucitron.services.<key> = {` assignments. */
export function splitServices(source) {
  const lines = source.split('\n');
  const fragments = [];
  for (let i = 0; i < lines.length; i += 1) {
    const match = START.exec(lines[i]);
    if (!match) continue;
    let end = -1;
    for (let j = i + 1; j < lines.length; j += 1) {
      if (END.test(lines[j])) { end = j; break; }
      if (START.test(lines[j])) break;
    }
    if (end === -1) throw new Error(`Fragment "${match[1]}" (line ${i + 1}): no closing "};" at column 0.`);
    fragments.push({ key: match[1], line: i + 1, code: lines.slice(i, end + 1).join('\n') + '\n' });
    i = end;
  }
  const seen = new Set();
  const lower = new Set();
  for (const { key } of fragments) {
    if (seen.has(key)) throw new Error(`Duplicate service key "${key}".`);
    if (lower.has(key.toLowerCase())) throw new Error(`Service keys differing only by case: "${key}" (file name collision).`);
    seen.add(key);
    lower.add(key.toLowerCase());
  }
  return fragments;
}
