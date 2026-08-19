#!/usr/bin/env python3
"""
colorspaces.py — accurate conversions used to fact-check the articles.

    python3 tools/colorspaces.py oklch 003566
    python3 tools/colorspaces.py mix oklch 003566 FFFFFF 40
    python3 tools/colorspaces.py hwb FAF2E0
    python3 tools/colorspaces.py p3 00E6FF
    python3 tools/colorspaces.py rcs 994129 --dl 0.15        # relative-colour preview

Implements sRGB <-> linear, XYZ D65, Oklab/OKLCH (Ottosson), HWB and
Display-P3, so any value quoted in a post can be verified.
"""

from __future__ import annotations

import math
import sys

# ----------------------------------------------------------------- sRGB basics


def hex_to_rgb(h: str) -> tuple[float, float, float]:
    h = h.strip().lstrip("#")
    if len(h) == 3:
        h = "".join(c * 2 for c in h)
    return tuple(int(h[i:i + 2], 16) / 255 for i in (0, 2, 4))  # type: ignore


def rgb_to_hex(rgb: tuple[float, float, float]) -> str:
    return "#" + "".join(f"{round(max(0.0, min(1.0, c)) * 255):02X}" for c in rgb)


def srgb_to_linear(c: float) -> float:
    return c / 12.92 if c <= 0.04045 else ((c + 0.055) / 1.055) ** 2.4


def linear_to_srgb(c: float) -> float:
    return 12.92 * c if c <= 0.0031308 else 1.055 * (c ** (1 / 2.4)) - 0.055


def _mul(m: list[list[float]], v: tuple[float, float, float]) -> tuple[float, float, float]:
    return tuple(sum(m[i][j] * v[j] for j in range(3)) for i in range(3))  # type: ignore


# ------------------------------------------------------------------- Oklab/LCH
# Björn Ottosson, https://bottosson.github.io/posts/oklab/


def linear_to_oklab(rgb: tuple[float, float, float]) -> tuple[float, float, float]:
    r, g, b = rgb
    l = 0.4122214708 * r + 0.5363325363 * g + 0.0514459929 * b
    m = 0.2119034982 * r + 0.6806995451 * g + 0.1073969566 * b
    s = 0.0883024619 * r + 0.2817188376 * g + 0.6299787005 * b
    l_, m_, s_ = (math.copysign(abs(x) ** (1 / 3), x) for x in (l, m, s))
    return (
        0.2104542553 * l_ + 0.7936177850 * m_ - 0.0040720468 * s_,
        1.9779984951 * l_ - 2.4285922050 * m_ + 0.4505937099 * s_,
        0.0259040371 * l_ + 0.7827717662 * m_ - 0.8086757660 * s_,
    )


def oklab_to_linear(lab: tuple[float, float, float]) -> tuple[float, float, float]:
    L, a, b = lab
    l_ = L + 0.3963377774 * a + 0.2158037573 * b
    m_ = L - 0.1055613458 * a - 0.0638541728 * b
    s_ = L - 0.0894841775 * a - 1.2914855480 * b
    l, m, s = (x ** 3 for x in (l_, m_, s_))
    return (
        +4.0767416621 * l - 3.3077115913 * m + 0.2309699292 * s,
        -1.2684380046 * l + 2.6097574011 * m - 0.3413193965 * s,
        -0.0041960863 * l - 0.7034186147 * m + 1.7076147010 * s,
    )


def hex_to_oklch(h: str) -> tuple[float, float, float]:
    lin = tuple(srgb_to_linear(c) for c in hex_to_rgb(h))
    L, a, b = linear_to_oklab(lin)  # type: ignore
    C = math.hypot(a, b)
    H = math.degrees(math.atan2(b, a)) % 360
    return round(L, 4), round(C, 4), round(H, 2)


def oklch_to_hex(L: float, C: float, H: float) -> tuple[str, bool]:
    a = C * math.cos(math.radians(H))
    b = C * math.sin(math.radians(H))
    lin = oklab_to_linear((L, a, b))
    clipped = any(c < -0.0001 or c > 1.0001 for c in lin)
    return rgb_to_hex(tuple(linear_to_srgb(c) for c in lin)), clipped  # type: ignore


# ------------------------------------------------------------------------ HWB


def hex_to_hwb(h: str) -> tuple[float, float, float]:
    r, g, b = hex_to_rgb(h)
    mx, mn = max(r, g, b), min(r, g, b)
    d = mx - mn
    if d == 0:
        hue = 0.0
    elif mx == r:
        hue = ((g - b) / d) % 6
    elif mx == g:
        hue = (b - r) / d + 2
    else:
        hue = (r - g) / d + 4
    return round(hue * 60 % 360), round(mn * 100), round((1 - mx) * 100)


def hwb_to_hex(H: float, W: float, B: float) -> str:
    w, bl = W / 100, B / 100
    if w + bl >= 1:                      # CSS normalisation rule → grey
        grey = w / (w + bl)
        return rgb_to_hex((grey, grey, grey))
    # hue -> pure rgb
    hp = (H % 360) / 60
    x = 1 - abs(hp % 2 - 1)
    i = int(hp)
    pure = [(1, x, 0), (x, 1, 0), (0, 1, x), (0, x, 1), (x, 0, 1), (1, 0, x)][i % 6]
    return rgb_to_hex(tuple(c * (1 - w - bl) + w for c in pure))  # type: ignore


# ------------------------------------------------------------------ Display P3

SRGB_TO_XYZ = [[0.4124564, 0.3575761, 0.1804375],
               [0.2126729, 0.7151522, 0.0721750],
               [0.0193339, 0.1191920, 0.9503041]]
XYZ_TO_P3 = [[2.4934969, -0.9313836, -0.4027108],
             [-0.8294890, 1.7626641, 0.0236247],
             [0.0358458, -0.0761724, 0.9568845]]
P3_TO_XYZ = [[0.4865709, 0.2656677, 0.1982173],
             [0.2289746, 0.6917385, 0.0792869],
             [0.0000000, 0.0451134, 1.0439444]]
XYZ_TO_SRGB = [[3.2404542, -1.5371385, -0.4985314],
               [-0.9692660, 1.8760108, 0.0415560],
               [0.0556434, -0.2040259, 1.0572252]]


def hex_to_p3(h: str) -> tuple[float, float, float]:
    lin = tuple(srgb_to_linear(c) for c in hex_to_rgb(h))
    xyz = _mul(SRGB_TO_XYZ, lin)  # type: ignore
    p3lin = _mul(XYZ_TO_P3, xyz)
    return tuple(round(linear_to_srgb(max(0.0, min(1.0, c))), 4) for c in p3lin)  # type: ignore


def p3_to_hex(r: float, g: float, b: float) -> tuple[str, bool]:
    lin = tuple(srgb_to_linear(c) for c in (r, g, b))
    xyz = _mul(P3_TO_XYZ, lin)  # type: ignore
    srgb_lin = _mul(XYZ_TO_SRGB, xyz)
    out = any(c < -0.0005 or c > 1.0005 for c in srgb_lin)
    return rgb_to_hex(tuple(linear_to_srgb(max(0.0, min(1.0, c))) for c in srgb_lin)), out  # type: ignore


# ------------------------------------------------------------------ Adobe RGB

XYZ_TO_ADOBE = [[2.0413690, -0.5649464, -0.3446944],
                [-0.9692660, 1.8760108, 0.0415560],
                [0.0134474, -0.1183897, 1.0154096]]
ADOBE_TO_XYZ = [[0.5767309, 0.1855540, 0.1881852],
                [0.2973769, 0.6273491, 0.0752741],
                [0.0270343, 0.0706872, 0.9911085]]
ADOBE_GAMMA = 563 / 256          # 2.19921875


def hex_to_adobe(h: str) -> tuple[float, float, float]:
    lin = tuple(srgb_to_linear(c) for c in hex_to_rgb(h))
    xyz = _mul(SRGB_TO_XYZ, lin)  # type: ignore
    a = _mul(XYZ_TO_ADOBE, xyz)
    return tuple(round(max(0.0, min(1.0, c)) ** (1 / ADOBE_GAMMA), 4) for c in a)  # type: ignore


def adobe_to_hex(r: float, g: float, b: float) -> tuple[str, bool]:
    lin = tuple(c ** ADOBE_GAMMA for c in (r, g, b))
    xyz = _mul(ADOBE_TO_XYZ, lin)  # type: ignore
    s = _mul(XYZ_TO_SRGB, xyz)
    out = any(c < -0.0005 or c > 1.0005 for c in s)
    return rgb_to_hex(tuple(linear_to_srgb(max(0.0, min(1.0, c))) for c in s)), out  # type: ignore


# ------------------------------------------------------------------- color-mix


def mix(space: str, a: str, b: str, pct_b: float = 50.0) -> str:
    """color-mix(in <space>, a, b pct_b%) for srgb / oklab / oklch."""
    t = pct_b / 100
    if space == "srgb":
        ca, cb = hex_to_rgb(a), hex_to_rgb(b)
        return rgb_to_hex(tuple(x * (1 - t) + y * t for x, y in zip(ca, cb)))  # type: ignore
    if space == "srgb-linear":
        ca = tuple(srgb_to_linear(c) for c in hex_to_rgb(a))
        cb = tuple(srgb_to_linear(c) for c in hex_to_rgb(b))
        return rgb_to_hex(tuple(linear_to_srgb(x * (1 - t) + y * t)
                                for x, y in zip(ca, cb)))  # type: ignore
    la = linear_to_oklab(tuple(srgb_to_linear(c) for c in hex_to_rgb(a)))  # type: ignore
    lb = linear_to_oklab(tuple(srgb_to_linear(c) for c in hex_to_rgb(b)))  # type: ignore
    if space == "oklab":
        lab = tuple(x * (1 - t) + y * t for x, y in zip(la, lb))
        return rgb_to_hex(tuple(linear_to_srgb(c) for c in oklab_to_linear(lab)))  # type: ignore
    # oklch: interpolate L, C and hue (shorter arc)
    La, Ca, Ha = hex_to_oklch(a)
    Lb, Cb, Hb = hex_to_oklch(b)
    if Ca < 1e-6:
        Ha = Hb
    if Cb < 1e-6:
        Hb = Ha
    dh = ((Hb - Ha + 180) % 360) - 180
    L = La * (1 - t) + Lb * t
    C = Ca * (1 - t) + Cb * t
    H = (Ha + dh * t) % 360
    return oklch_to_hex(L, C, H)[0]


# ------------------------------------------------------------------------- cli


def main() -> int:
    if len(sys.argv) < 2:
        print(__doc__)
        return 1
    cmd = sys.argv[1]
    if cmd == "oklch":
        for h in sys.argv[2:]:
            L, C, H = hex_to_oklch(h)
            print(f"#{h.lstrip('#').upper()} -> oklch({L:.4f} {C:.4f} {H:.2f})")
    elif cmd == "mix":
        space, a, b = sys.argv[2], sys.argv[3], sys.argv[4]
        pct = float(sys.argv[5]) if len(sys.argv) > 5 else 50.0
        print(f"color-mix(in {space}, #{a}, #{b} {pct:g}%) = {mix(space, a, b, pct)}")
    elif cmd == "hwb":
        for h in sys.argv[2:]:
            H, W, B = hex_to_hwb(h)
            print(f"#{h.lstrip('#').upper()} -> hwb({H} {W}% {B}%)  "
                  f"round-trip {hwb_to_hex(H, W, B)}")
    elif cmd == "adobe":
        for h in sys.argv[2:]:
            r, g, b = hex_to_adobe(h)
            print(f"#{h.lstrip('#').upper()} -> Adobe RGB ({r:.4f} {g:.4f} {b:.4f})")
    elif cmd == "p3":
        for h in sys.argv[2:]:
            r, g, b = hex_to_p3(h)
            print(f"#{h.lstrip('#').upper()} -> color(display-p3 {r:.4f} {g:.4f} {b:.4f})")
    elif cmd == "rcs":
        base = sys.argv[2]
        dl = float(sys.argv[sys.argv.index("--dl") + 1]) if "--dl" in sys.argv else 0.0
        cm = float(sys.argv[sys.argv.index("--cm") + 1]) if "--cm" in sys.argv else 1.0
        dh = float(sys.argv[sys.argv.index("--dh") + 1]) if "--dh" in sys.argv else 0.0
        L, C, H = hex_to_oklch(base)
        hexout, clipped = oklch_to_hex(L + dl, C * cm, (H + dh) % 360)
        print(f"oklch(from #{base.upper()} calc(l + {dl}) calc(c * {cm}) calc(h + {dh})) "
              f"= {hexout}{'  [gamut-clipped]' if clipped else ''}")
    else:
        print(__doc__)
        return 1
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
