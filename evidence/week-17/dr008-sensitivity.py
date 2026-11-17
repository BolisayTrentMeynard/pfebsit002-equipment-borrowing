"""DR-008 scoring and sensitivity test (Week 17, chunk 2a).

Standard library only (Python 3). Run:  python dr008-sensitivity.py
The weights and the sensitivity method are the ones committed in DR-008 (commit 6c3849b).
Only the SCORES below were added in chunk 2. Change a score here, rerun, and copy the new output
into decision-record-008-architecture-alternatives.md (and log the change with its date).
"""

CRITERIA = ["Reliability", "Security", "Supportability", "Complexity",
            "Change impact", "Skills", "Cost", "Data ownership"]
WEIGHTS = [20, 15, 15, 12, 10, 10, 10, 8]          # committed in 6c3849b, total 100

SCORES = {                                           # 1 to 5, 5 best (see DR-008 for the reasons)
    "A": [2, 2, 4, 5, 4, 5, 5, 5],
    "B": [4, 4, 3, 3, 4, 3, 4, 5],
    "C": [4, 3, 2, 2, 2, 1, 2, 3],
}
assert sum(WEIGHTS) == 100 and all(len(s) == 8 for s in SCORES.values())


def total(weights, scores):
    return sum(w * s for w, s in zip(weights, scores)) / sum(weights)


def scale_others(weights, idx_new):
    """idx_new: {index: new weight}. All other weights are scaled in proportion so the total stays 100."""
    fixed = sum(idx_new.values())
    others = sum(w for i, w in enumerate(weights) if i not in idx_new)
    k = (100 - fixed) / others
    return [idx_new[i] if i in idx_new else w * k for i, w in enumerate(weights)]


def ranking(weights):
    res = {o: total(weights, s) for o, s in SCORES.items()}
    order = sorted(res, key=lambda o: -res[o])
    return res, order


def line(label, weights):
    res, order = ranking(weights)
    top = order[0]
    margin = res[order[0]] - res[order[1]]
    tie = " (tie within 0.05)" if margin < 0.05 else ""
    return (f"{label:<34} A {res['A']:.2f}  B {res['B']:.2f}  C {res['C']:.2f}   "
            f"order {' > '.join(order)}   top {top}, lead {margin:.2f}{tie}"), top


def main():
    out = []
    out.append("DR-008 weighted scores (weight x score / 100)")
    out.append("")
    out.append(f"{'Criterion':<16}{'Weight':>7}{'A':>4}{'B':>4}{'C':>4}")
    for c, w, a, b, cc in zip(CRITERIA, WEIGHTS, SCORES["A"], SCORES["B"], SCORES["C"]):
        out.append(f"{c:<16}{w:>7}{a:>4}{b:>4}{cc:>4}")
    out.append("")
    out.append("Weighted points per option (sum of weight x score; divide by 100 for the score):")
    for o in "ABC":
        pts = sum(w * s for w, s in zip(WEIGHTS, SCORES[o]))
        out.append(f"  {o}: {pts}  ->  {pts/100:.2f}")
    out.append("")

    tops = {}
    cases = []
    cases.append(("1 Baseline", [float(w) for w in WEIGHTS]))
    cases.append(("2 Equal weights (12.5 each)", [12.5] * 8))
    cases.append(("3 Reliability-heavy (35)", scale_others(WEIGHTS, {0: 35})))
    cases.append(("4 Security-heavy (30)", scale_others(WEIGHTS, {1: 30})))
    cases.append(("5 Cost and skills heavy (20, 20)", scale_others(WEIGHTS, {6: 20, 5: 20})))
    out.append("Profiles (steps 1 to 5 of the committed method)")
    for label, w in cases:
        text, top = line(label, w)
        out.append(text)
        tops[label] = top
    out.append("")

    out.append("Step 6: each weight moved by +10 and -10 points, the others scaled to keep the total at 100")
    out.append("(a weight cannot go below 0: Data ownership -10 is set to 0, a change of -8)")
    flips = []
    for i, c in enumerate(CRITERIA):
        for delta in (+10, -10):
            new = max(0, WEIGHTS[i] + delta)
            w = scale_others(WEIGHTS, {i: new})
            label = f"{c} {new - WEIGHTS[i]:+d} (to {new})"
            text, top = line(label, w)
            out.append(text)
            tops[label] = top
    out.append("")

    out.append("Does the top option ever change?")
    names = sorted(set(tops.values()))
    out.append(f"  Top option over all {len(tops)} cases: {', '.join(names)}")
    for o in names:
        out.append(f"  {o} on top in {sum(1 for v in tops.values() if v == o)} cases")
    out.append("")

    out.append("Break-even weight for A versus B (one weight changed, the others scaled; where the score gap is 0)")
    d = [a - b for a, b in zip(SCORES["A"], SCORES["B"])]
    T = sum(w * x for w, x in zip(WEIGHTS, d))            # A minus B in weighted points at baseline
    out.append(f"  Baseline gap A minus B: {T} weighted points = {T/100:+.2f} score points")
    for i, c in enumerate(CRITERIA):
        if d[i] == 0:
            out.append(f"  {c:<16} A and B score the same here, so changing this weight never separates them by itself")
            continue
        w0 = WEIGHTS[i]
        # gap(w) = w*d_i + (100-w)/(100-w0) * (T - w0*d_i) ; solve gap(w) = 0
        k = (T - w0 * d[i]) / (100 - w0)
        w_star = -100 * k / (d[i] - k)
        out.append(f"  {c:<16} A = B at weight {w_star:5.1f} (baseline {w0}); A is ahead {'above' if d[i] > 0 else 'below'} that weight, B ahead {'below' if d[i] > 0 else 'above'} it")
    out.append("")

    out.append("Score sensitivity (baseline weights): does a one-point change in one score flip A and B?")
    for o in "AB":
        for i, c in enumerate(CRITERIA):
            for delta in (+1, -1):
                s = SCORES[o][i] + delta
                if not 1 <= s <= 5:
                    continue
                alt = {k: list(v) for k, v in SCORES.items()}
                alt[o][i] = s
                a = total(WEIGHTS, alt["A"]); b = total(WEIGHTS, alt["B"])
                lead = "A" if a > b else ("B" if b > a else "tie")
                out.append(f"  {o} {c} {SCORES[o][i]} -> {s}: A {a:.2f}  B {b:.2f}  lead {lead}")
    print("\n".join(out))


if __name__ == "__main__":
    main()
