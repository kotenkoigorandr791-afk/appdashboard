export type LimitResult = { allowed: boolean; retryAfterMs: number };

type Bucket = { at: number[]; head: number };

/**
 * Sliding-window limiter with O(1) amortized expiry.
 *
 * The previous implementation called Array.shift() for every expired hit,
 * which re-indexes the whole bucket (O(n)) and becomes visible when the limit
 * or request rate is high. This keeps a head index and compacts the bucket
 * only when enough dead entries have accumulated. Semantics are unchanged:
 * at most `max` hits per `windowMs` per key, and Retry-After points at the
 * oldest hit still inside the window.
 */
export class SlidingWindowLimiter {
  private hits = new Map<string, Bucket>();

  constructor(private windowMs: number, private max: number) {}

  hit(key: string): LimitResult {
    const now = Date.now();
    let bucket = this.hits.get(key);
    if (!bucket) {
      bucket = { at: [], head: 0 };
      this.hits.set(key, bucket);
    }
    const at = bucket.at;
    while (bucket.head < at.length && now - at[bucket.head]! > this.windowMs) bucket.head++;

    if (at.length - bucket.head >= this.max) {
      const oldest = at[bucket.head]!;
      return { allowed: false, retryAfterMs: Math.max(1, this.windowMs - (now - oldest)) };
    }

    at.push(now);
    if (bucket.head > 1024 && bucket.head * 2 > at.length) {
      bucket.at = at.slice(bucket.head);
      bucket.head = 0;
    }
    if (this.hits.size > 10_000) {
      for (const [k, v] of this.hits) {
        if (v.head >= v.at.length || now - v.at[v.at.length - 1]! > this.windowMs * 2) this.hits.delete(k);
      }
    }
    return { allowed: true, retryAfterMs: 0 };
  }
}
