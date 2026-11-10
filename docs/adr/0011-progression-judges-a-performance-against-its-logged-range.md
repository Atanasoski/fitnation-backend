# Progression judges a performance against its Logged Range; a Deload Week holds the Working Weight

The Working Weight raise and `progression_status` used two different rep ceilings: the raise used the ADR-0009 matrix (by the exercise's own pattern), the status used the range the session row displayed. Once generated rows stored slot ranges (ADR-0010) the two disagreed on most generated rows — a weight raise with status "working", or "ready" with no raise. We decided that a working session's performance is judged once — topped, working, or below its minimum — against each set's **Logged Range** (the range its session row prescribed when the set was logged; the matrix range when the row stored none), and that one verdict drives both the raise and the status. Today's row range is only today's target. A Deload Week session holds the Working Weight in both directions: no raise and no lighter-weight offer.

## Considered Options

- **Judge against today's row range.** Rejected: deload rows prescribe 3–4 reps, so every deload session would read as topped and raise the weight; a range change between Mesocycles would also re-judge old performances against a range the member never trained in.
- **Judge against the matrix everywhere.** Rejected: generated rows display slot ranges, so members would be judged against a range they were never shown.

## Consequences

- Supersedes ADR-0007's invariant about deload reps staying below an experience-tier max (that mechanism is gone; ADR-0008 already keeps deload sessions out of history) and ADR-0009's transitional note (rows storing the old 8–12 bounds are now judged against those bounds, so display and raise agree).
- When one exercise occupies two rows of a session (a top set and a back-off block), each row's sets are judged against that row's range; the session tops only if every set topped its own.
- Sets on rows that stored no bounds are judged against the matrix range at read time — what the member was shown, unless the matrix has changed since.
