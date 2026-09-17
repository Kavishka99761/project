/**
 * KAVISHKA — retrieval engine (client mirror).
 *
 * 1:1 port of backend-laravel/app/Services/RetrievalService.php so the mobile
 * assistant returns the same grounded, source-cited answers whether the Laravel
 * API answered the question or the app is running offline against the bundled
 * knowledge base. Lexical scoring only — no LLM or vector DB required.
 *
 * Parity points that are easy to break — keep them in step with the PHP:
 *   MATCH_THRESHOLD 0.08 · keyword hit 3.0 · content hit 1 + log(freq)
 *   keywords tokenised before matching (so phrase keywords still work)
 *   normalise by (queryTokens.length + 1) · round(score, 4)
 *   excerpt: collapse whitespace, trim, then 397 chars + '...' when > 400
 * NOTE: frontend-web/assets/js/assistant.js is a separate, simplified scorer —
 * it is NOT a port of this file.
 */

const MATCH_THRESHOLD = 0.08;

const STOP = new Set([
  'what', 'when', 'where', 'how', 'why', 'the', 'a', 'an', 'is',
  'are', 'do', 'does', 'i', 'my', 'to', 'of', 'in', 'for', 'and', 'me',
]);

/** @returns {string[]} unique, lower-cased, meaningful tokens */
export function tokens(text) {
  const clean = String(text || '').replace(/<[^>]*>/g, ' ').toLowerCase();
  const matches = clean.match(/[a-z0-9]+/g) || [];
  const seen = new Set();
  const out = [];
  for (const t of matches) {
    if (t.length > 2 && !STOP.has(t) && !seen.has(t)) {
      seen.add(t);
      out.push(t);
    }
  }
  return out;
}

/**
 * Raw term counts — mirrors RetrievalService::termFrequency().
 *
 * Deliberately NOT built on tokens(): that helper de-duplicates, so counting its
 * output would pin every term to a frequency of 1 and collapse the `1 + log(freq)`
 * damping in score() to a flat `1 + 0`. Stop words and short terms may appear in
 * the counts — harmless, since query tokens are already filtered.
 */
function freq(text) {
  const clean = String(text || '').replace(/<[^>]*>/g, ' ').toLowerCase();
  const counts = new Map();
  const matches = clean.match(/[a-z0-9]+/g) || [];
  for (const t of matches) counts.set(t, (counts.get(t) || 0) + 1);
  return counts;
}

/**
 * Score a chunk against query tokens.
 * Keyword hits are the strongest signal (3.0); otherwise content term frequency
 * is rewarded with a log damping, then normalised by query size.
 */
export function score(queryTokens, chunk) {
  /* Authored keywords may be phrases ("special consideration", "late submission")
   * while queryTokens are always single words, so matching the raw strings against
   * each other makes every phrase keyword inert. Tokenising the keywords through the
   * same helper keeps phrases working and strips stop words out of them. Mirrors
   * RetrievalService::score(). */
  const keywords = new Set(tokens((chunk.keywords || []).map(String).join(' ')));
  const contentFreq = freq(`${chunk.content || ''} ${chunk.section || ''}`);

  let total = 0;
  for (const token of queryTokens) {
    if (keywords.has(token)) {
      total += 3.0;
      continue;
    }
    const hits = contentFreq.get(token);
    if (hits) total += 1.0 + Math.log(hits);
  }

  return total / (queryTokens.length + 1);
}

/** @returns {{chunk:object, score:number}[]} best first */
export function rank(query, chunks) {
  const queryTokens = tokens(query);
  if (!queryTokens.length) return [];

  return (chunks || [])
    .map((chunk) => ({ chunk, score: +score(queryTokens, chunk).toFixed(4) }))
    .filter((r) => r.score > 0)
    .sort((a, b) => b.score - a.score);
}

/** Best chunk above the match threshold, or null. */
export function best(query, chunks) {
  const ranked = rank(query, chunks);
  if (!ranked.length || ranked[0].score < MATCH_THRESHOLD) return null;
  return ranked[0];
}

/** Compose the grounded answer text, citing section and page. */
export function compose(chunk) {
  const full = String(chunk.content || '').replace(/\s+/g, ' ').trim();
  const excerpt = full.length > 400 ? `${full.slice(0, 397)}...` : full;
  let where = chunk.section || 'the document';
  if (chunk.page) where += ` (p. ${chunk.page})`;
  return `Based on ${where}: ${excerpt}`;
}

/**
 * Build the bot reply for a question.
 * @returns {{answer:string, source:object|null}}
 */
export function answer(query, chunks) {
  const hit = best(query, chunks);

  if (!hit) {
    return {
      answer:
        "I couldn't find that in your indexed academic documents. Try rephrasing, or add the relevant handbook/guidelines to the knowledge base so I can ground my answer with a source reference.",
      source: null,
    };
  }

  const { chunk } = hit;
  return {
    answer: compose(chunk),
    source: {
      document: chunk.documentTitle || null,
      category: chunk.category || null,
      section: chunk.section || null,
      page: chunk.page || null,
      score: hit.score,
      // snake_case on purpose: matches the API's source payload key-for-key, so a
      // source object from either path is interchangeable in AssistantScreen.
      chunk_id: chunk.id ?? null,
    },
  };
}

/**
 * Flatten the bundled knowledge base (documents → sections) into chunk objects
 * shaped like the API's academic_chunks rows, so `answer()` works offline.
 */
export function flattenKnowledgeBase(docs) {
  const chunks = [];
  (docs || []).forEach((doc) => {
    (doc.sections || []).forEach((s, i) => {
      chunks.push({
        id: `${doc.id}-${i}`,
        documentTitle: doc.title,
        category: doc.category,
        section: s.section,
        page: s.page,
        keywords: s.keywords || [],
        content: s.content || '',
      });
    });
  });
  return chunks;
}

export default { tokens, score, rank, best, compose, answer, flattenKnowledgeBase, MATCH_THRESHOLD };
