#!/usr/bin/env python3
"""SUGGAWAYZ asset generator - generates header, footer, OG image, and product placeholders."""

from PIL import Image, ImageDraw, ImageFont, ImageFilter, ImageEnhance
import os
import math

SRC_DIR = "assets"
PUBLIC_DIR = "public/assets/img"
WIDTH = 1920
HEADER_HEIGHT = 780
FOOTER_HEIGHT = 860
OG_SIZE = (1200, 630)

def get_font(name, size):
    paths = [
        f"C:/Windows/Fonts/{name}.ttf",
        f"C:/Windows/Fonts/{name.lower()}.ttf",
        f"/usr/share/fonts/truetype/{name}/{name}.ttf",
    ]
    for p in paths:
        if os.path.exists(p):
            return ImageFont.truetype(p, size)
    return ImageFont.load_default()

def make_header():
    bg_path = os.path.join(SRC_DIR, "img", "background.png")
    bg = None
    if os.path.exists(bg_path):
        bg = Image.open(bg_path).convert("RGBA").resize((WIDTH, HEADER_HEIGHT), Image.LANCZOS)
    else:
        bg = Image.new("RGBA", (WIDTH, HEADER_HEIGHT), (2, 6, 17, 255))
    
    enhancer = ImageEnhance.Contrast(bg)
    bg = enhancer.enhance(1.4)
    enhancer = ImageEnhance.Brightness(bg)
    bg = enhancer.enhance(0.55)
    
    overlay = Image.new("RGBA", bg.size, (2, 6, 17, 0))
    draw = ImageDraw.Draw(overlay)
    for y in range(0, HEADER_HEIGHT, 4):
        alpha = int(80 * (1 - y / HEADER_HEIGHT))
        draw.line([(0, y), (WIDTH, y)], fill=(2, 6, 17, alpha))
    bg = Image.alpha_composite(bg, overlay)
    
    draw = ImageDraw.Draw(bg)
    
    glow_color = (0, 140, 255, 60)
    for i in range(3):
        x = 200 + i * 600
        draw.ellipse([x-200, 100, x+200, HEADER_HEIGHT-100], fill=glow_color)
    
    font_large = get_font("bahnschrift", 160) or get_font("arial", 160)
    font_small = get_font("bahnschrift", 60) or get_font("arial", 60)
    font_med = get_font("bahnschrift", 36) or get_font("arial", 36)
    font_tag = get_font("segoeui", 24) or get_font("arial", 24)
    
    draw.text((WIDTH - 100, 220), "SW", fill=(0, 140, 255, 180), font=font_large, anchor="rb")
    draw.text((WIDTH - 100, 280), "SUGGAWAYZ", fill=(245, 248, 255, 220), font=font_large, anchor="rb")
    draw.text((WIDTH - 100, 400), "BE DIFFERENT. BE YOU.", fill=(0, 200, 255, 200), font=font_med, anchor="rb")
    draw.text((WIDTH - 100, 460), "EST. 2024", fill=(184, 199, 217, 150), font=font_tag, anchor="rb")
    
    if bg.mode != "RGB":
        bg = bg.convert("RGB")
    bg = bg.filter(ImageFilter.UnsharpMask(radius=1.5, percent=80, threshold=3))
    
    out = os.path.join(PUBLIC_DIR, "header.png")
    bg.save(out, "PNG")
    print(f"Generated {out}")

def make_footer():
    bg_path = os.path.join(SRC_DIR, "img", "background.png")
    bg = None
    if os.path.exists(bg_path):
        bg = Image.open(bg_path).convert("RGBA").resize((WIDTH, FOOTER_HEIGHT), Image.LANCZOS)
    else:
        bg = Image.new("RGBA", (WIDTH, FOOTER_HEIGHT), (0, 4, 12, 255))
    
    enhancer = ImageEnhance.Brightness(bg)
    bg = enhancer.enhance(0.3)
    
    overlay = Image.new("RGBA", bg.size, (0, 4, 12, 0))
    draw = ImageDraw.Draw(overlay)
    draw.rectangle([0, 0, WIDTH, FOOTER_HEIGHT], fill=(0, 4, 12, 200))
    bg = Image.composite(overlay, bg, overlay)
    
    draw = ImageDraw.Draw(bg)
    draw.line([(100, 100), (WIDTH-100, 100)], fill=(0, 140, 255, 100), width=1)
    
    font_large = get_font("bahnschrift", 80) or get_font("arial", 80)
    font_small = get_font("bahnschrift", 28) or get_font("arial", 28)
    
    draw.text((WIDTH//2, 200), "BE DIFFERENT. BE YOU.", fill=(245, 248, 255, 200), font=font_large, anchor="mt")
    draw.text((WIDTH//2, 280), "JOIN THE MOVEMENT", fill=(0, 200, 255, 180), font=font_small, anchor="mt")
    
    if bg.mode != "RGB":
        bg = bg.convert("RGB")
    
    out = os.path.join(PUBLIC_DIR, "footer.png")
    bg.save(out, "PNG")
    print(f"Generated {out}")

def make_og():
    img = Image.new("RGB", OG_SIZE, (2, 6, 17, 255))
    draw = ImageDraw.Draw(img)
    font = get_font("bahnschrift", 100) or get_font("arial", 100)
    font_s = get_font("bahnschrift", 40) or get_font("arial", 40)
    
    draw.text((OG_SIZE[0]//2, OG_SIZE[1]//2 - 40), "SUGGAWAYZ", fill=(0, 140, 255, 255), font=font, anchor="mt")
    draw.text((OG_SIZE[0]//2, OG_SIZE[1]//2 + 60), "FUTURISTIC STREETWEAR", fill=(245, 248, 255, 200), font=font_s, anchor="mt")
    
    out = os.path.join(PUBLIC_DIR, "og-default.png")
    img.save(out, "PNG")
    print(f"Generated {out}")

def make_product_placeholders():
    names = [
        "shadow-hoodie-1", "shadow-hoodie-2",
        "cyber-tee-1", "cyber-tee-2",
        "future-jacket-1", "future-jacket-2",
        "neon-joggers-1",
        "holo-cap-1",
        "cybermask-1",
        "cargo-tech-1",
        "neon-genesis-1",
        "phantom-mesh-1",
        "signal-blue-1",
    ]
    colors = [
        (20, 20, 30), (0, 100, 180),
        (30, 30, 40), (200, 200, 220),
        (40, 40, 50), (100, 100, 120),
        (30, 30, 40),
        (20, 20, 30),
        (25, 25, 35),
        (35, 35, 45),
        (10, 10, 20),
        (30, 30, 40),
        (0, 80, 160),
    ]
    
    for name, color in zip(names, colors):
        img = Image.new("RGB", (600, 750), color)
        draw = ImageDraw.Draw(img)
        font = get_font("bahnschrift", 36) or get_font("arial", 36)
        
        for i in range(5):
            x = 50 + i * 120
            draw.rectangle([x, 200, x+2, 550], fill=(0, 140, 255, 40))
        
        draw.text((300, 375), "SW", fill=(0, 140, 255, 120), font=font, anchor="mt")
        draw.text((300, 420), name.replace("-", " ").title(), fill=(200, 200, 220, 150), font=get_font("arial", 20) or ImageFont.load_default(), anchor="mt")
        
        out = os.path.join(PUBLIC_DIR, "products", f"{name}.png")
        img.save(out, "PNG")
        print(f"Generated {out}")

if __name__ == "__main__":
    os.makedirs(PUBLIC_DIR, exist_ok=True)
    os.makedirs(os.path.join(PUBLIC_DIR, "products"), exist_ok=True)
    make_header()
    make_footer()
    make_og()
    make_product_placeholders()
    print("All assets generated successfully.")
