#!/usr/bin/env python3
"""
Cyclowax klantenbestand → match-rapport generator.

Stap 1: Parse XLSX, voeg multi-row records samen, schoon op
Stap 2: Fuzzy match tegen bestaande stores in SQLite
Stap 3: Genereer match-rapport CSV voor review
"""

import pandas as pd
import sqlite3
import os
import re
from rapidfuzz import fuzz
from collections import defaultdict

# --- Config ---
XLSX_PATH = os.path.expanduser("~/Desktop/bikeshops-cyclowax.xlsx")
DB_PATH = os.path.join(os.path.dirname(__file__), "..", "database", "database.sqlite")
OUTPUT_DIR = os.path.join(os.path.dirname(__file__), "..", "storage", "app")
CLEAN_CSV = os.path.join(OUTPUT_DIR, "cyclowax_clean.csv")
MATCH_REPORT = os.path.join(OUTPUT_DIR, "cyclowax_match_report.csv")

# Minimum fuzzy score om als match te beschouwen
MATCH_THRESHOLD = 65

# Land-mapping (Odoo naam → ISO code)
COUNTRY_MAP = {
    "België": "BE",
    "Duitsland": "DE",
    "Nederland": "NL",
    "Frankrijk": "FR",
    "Italië": "IT",
    "Spanje": "ES",
    "Verenigd Koninkrijk": "GB",
    "Oostenrijk": "AT",
    "Zwitserland": "CH",
    "Luxemburg": "LU",
    "Portugal": "PT",
    "Denemarken": "DK",
    "Zweden": "SE",
    "Noorwegen": "NO",
    "Finland": "FI",
    "Polen": "PL",
    "Tsjechië": "CZ",
    "Ierland": "IE",
    "Griekenland": "GR",
    "Roemenië": "RO",
    "Kroatië": "HR",
    "Hongarije": "HU",
    "Slovenië": "SI",
    "Slowakije": "SK",
    "Bulgarije": "BG",
    "Estland": "EE",
    "Letland": "LV",
    "Litouwen": "LT",
    "Cyprus": "CY",
    "Malta": "MT",
    "Verenigde Staten": "US",
    "Canada": "CA",
    "Australië": "AU",
    "Japan": "JP",
    "China": "CN",
    "India": "IN",
    "Brazilië": "BR",
    "Mexico": "MX",
    "Zuid-Korea": "KR",
    "Turkije": "TR",
    "Israël": "IL",
    "Colombia": "CO",
    "Singapore": "SG",
    "Nieuw-Zeeland": "NZ",
}

# Label-categorieën voor shop_type extractie
SHOP_TYPE_LABELS = {
    "Bike Shop": "bike_shop",
    "Online retailer": "online_retailer",
    "Distributor": "distributor",
    "SUPPLIER": "supplier",
}


def parse_xlsx():
    """Stap 1: Parse XLSX en voeg multi-row records samen."""
    print("=" * 60)
    print("STAP 1: XLSX parsing en opschoning")
    print("=" * 60)

    df = pd.read_excel(XLSX_PATH, engine="openpyxl")

    # Identificeer record-rijen: hebben Land EN Aangemaakt op
    records = []
    current_record = None

    for _, row in df.iterrows():
        has_land = pd.notna(row.get("Land"))
        has_created = pd.notna(row.get("Aangemaakt op"))
        has_name = pd.notna(row.get("Schermnaam"))
        label = str(row.get("Labels", "")).strip() if pd.notna(row.get("Labels")) else ""

        # Sectieheaders herkennen (bijv. "België (96)")
        if has_name and not has_land and not has_created:
            name = str(row["Schermnaam"]).strip()
            if re.match(r".+\(\d+\)$", name):
                continue  # Skip section header

        # Nieuw record
        if has_land and has_created and has_name:
            if current_record:
                records.append(current_record)
            current_record = {
                "schermnaam": str(row["Schermnaam"]).strip(),
                "telefoon": str(row["Telefoon"]).strip() if pd.notna(row.get("Telefoon")) else "",
                "email": str(row["E-mail"]).strip() if pd.notna(row.get("E-mail")) else "",
                "verkoper": str(row["Verkoper"]).strip() if pd.notna(row.get("Verkoper")) else "",
                "stad": str(row["Plaats"]).strip() if pd.notna(row.get("Plaats")) else "",
                "land_naam": str(row["Land"]).strip(),
                "orders": int(row["Totaal verkooporders"]) if pd.notna(row.get("Totaal verkooporders")) else 0,
                "gefactureerd": float(row["Totaal gefactureerd"]) if pd.notna(row.get("Totaal gefactureerd")) else 0.0,
                "btw_nr": str(row["Btw nr."]).strip() if pd.notna(row.get("Btw nr.")) else "",
                "aangemaakt_op": str(row["Aangemaakt op"]),
                "labels": [],
            }
            if label:
                current_record["labels"].append(label)
        elif current_record and label:
            # Extra label-rij voor het huidige record
            current_record["labels"].append(label)

    # Laatste record toevoegen
    if current_record:
        records.append(current_record)

    print(f"  Gevonden: {len(records)} records")

    # Verrijk records
    clean = []
    for rec in records:
        # Land-mapping
        land_code = COUNTRY_MAP.get(rec["land_naam"], rec["land_naam"])

        # Shop type uit labels
        shop_type = ""
        for label_text in rec["labels"]:
            for label_key, type_val in SHOP_TYPE_LABELS.items():
                if label_key.lower() in label_text.lower():
                    shop_type = type_val
                    break

        # Pipeline status
        pipeline_status = "partner" if rec["orders"] > 0 else "gecontacteerd"

        # Naam opschonen: soms staat de handelsnaam tussen haakjes
        display_name = rec["schermnaam"]
        trade_name = ""
        match = re.search(r"\(([^)]+)\)", display_name)
        if match:
            trade_name = match.group(1).strip()

        all_labels = ", ".join(rec["labels"])

        clean.append({
            "schermnaam": display_name,
            "handelsnaam": trade_name,
            "telefoon": rec["telefoon"],
            "email": rec["email"],
            "verkoper": rec["verkoper"],
            "stad": rec["stad"],
            "land": land_code,
            "land_naam": rec["land_naam"],
            "orders": rec["orders"],
            "gefactureerd": rec["gefactureerd"],
            "btw_nr": rec["btw_nr"],
            "pipeline_status": pipeline_status,
            "shop_type": shop_type,
            "labels": all_labels,
            "aangemaakt_op": rec["aangemaakt_op"],
        })

    clean_df = pd.DataFrame(clean)
    clean_df.to_csv(CLEAN_CSV, index=False)
    print(f"  Opgeslagen: {CLEAN_CSV}")

    # Stats
    partners = clean_df[clean_df["pipeline_status"] == "partner"]
    prospects = clean_df[clean_df["pipeline_status"] == "gecontacteerd"]
    print(f"\n  Partners (1+ orders): {len(partners)}")
    print(f"  Gecontacteerd (0 orders): {len(prospects)}")
    print(f"\n  Landen: {clean_df['land'].value_counts().to_dict()}")

    return clean_df


def load_existing_stores():
    """Laad bestaande stores uit SQLite."""
    conn = sqlite3.connect(DB_PATH)
    query = "SELECT id, name, city, country, postal_code, phone, email, website FROM stores"
    stores_df = pd.read_sql(query, conn)
    conn.close()
    return stores_df


def normalize_name(name):
    """Normaliseer winkelnaam voor matching."""
    if not name or pd.isna(name):
        return ""
    name = str(name).lower().strip()
    # Verwijder juridische suffixen
    for suffix in ["bvba", "bv", "nv", "sa", "gmbh", "e.k.", "ohg", "kg", "ag",
                    "sprl", "srl", "sas", "sarl", "inc", "ltd", "llc", "co.",
                    "vof", "cv", "comm.v.", "b.v.", "n.v."]:
        name = re.sub(r"\b" + re.escape(suffix) + r"\.?\b", "", name)
    # Verwijder leestekens en extra spaties
    name = re.sub(r"[^\w\s]", " ", name)
    name = re.sub(r"\s+", " ", name).strip()
    return name


def normalize_city(city):
    """Normaliseer stadsnaam voor matching."""
    if not city or pd.isna(city):
        return ""
    city = str(city).lower().strip()
    city = re.sub(r"[^\w\s]", " ", city)
    city = re.sub(r"\s+", " ", city).strip()
    return city


def fuzzy_match(clean_df, stores_df):
    """Stap 2: Fuzzy matching van Cyclowax records tegen bestaande stores."""
    print("\n" + "=" * 60)
    print("STAP 2: Fuzzy matching")
    print("=" * 60)

    # Bouw index per land+stad voor snelle lookup
    store_index = defaultdict(list)
    for _, store in stores_df.iterrows():
        country = str(store["country"]).strip().upper() if pd.notna(store["country"]) else ""
        city = normalize_city(store["city"])
        store_index[(country, city)].append(store)

    results = []
    match_count = 0
    no_match_count = 0

    for _, rec in clean_df.iterrows():
        country = str(rec["land"]).strip().upper()
        city_norm = normalize_city(rec["stad"])
        name_norm = normalize_name(rec["schermnaam"])
        trade_norm = normalize_name(rec["handelsnaam"]) if rec["handelsnaam"] else ""

        best_match = None
        best_score = 0
        best_store_id = None
        best_store_name = ""

        # Zoek in zelfde land + zelfde stad
        candidates = store_index.get((country, city_norm), [])

        # Ook in variaties van de stadsnaam zoeken
        for key, stores in store_index.items():
            if key[0] == country and key[1] != city_norm:
                # Stad fuzzy match
                city_score = fuzz.ratio(city_norm, key[1])
                if city_score >= 85:
                    candidates.extend(stores)

        for store in candidates:
            store_name_norm = normalize_name(store["name"])

            # Score op basis van naam
            score_full = fuzz.token_sort_ratio(name_norm, store_name_norm)

            # Ook de handelsnaam proberen als die er is
            score_trade = 0
            if trade_norm:
                score_trade = fuzz.token_sort_ratio(trade_norm, store_name_norm)

            # Partial ratio voor gevallen waar de ene naam een subset is van de andere
            score_partial = fuzz.partial_ratio(name_norm, store_name_norm)
            score_partial_trade = 0
            if trade_norm:
                score_partial_trade = fuzz.partial_ratio(trade_norm, store_name_norm)

            score = max(score_full, score_trade, score_partial * 0.9, score_partial_trade * 0.9)

            if score > best_score:
                best_score = score
                best_store_id = store["id"]
                best_store_name = store["name"]

        if best_score >= MATCH_THRESHOLD:
            action = "UPDATE"
            match_count += 1
        else:
            action = "CREATE"
            no_match_count += 1

        results.append({
            "cyclowax_naam": rec["schermnaam"],
            "handelsnaam": rec["handelsnaam"],
            "stad": rec["stad"],
            "land": rec["land"],
            "orders": rec["orders"],
            "pipeline_status": rec["pipeline_status"],
            "telefoon": rec["telefoon"],
            "email": rec["email"],
            "verkoper": rec["verkoper"],
            "btw_nr": rec["btw_nr"],
            "shop_type": rec["shop_type"],
            "match_store_id": int(best_store_id) if best_score >= MATCH_THRESHOLD and pd.notna(best_store_id) else "",
            "match_store_naam": best_store_name if best_score >= MATCH_THRESHOLD else "",
            "confidence": round(best_score, 1),
            "actie": action,
        })

    print(f"  Matches (UPDATE): {match_count}")
    print(f"  Geen match (CREATE): {no_match_count}")

    return pd.DataFrame(results)


def generate_report(report_df):
    """Stap 3: Genereer match-rapport."""
    print("\n" + "=" * 60)
    print("STAP 3: Match-rapport")
    print("=" * 60)

    # Sorteer: eerst UPDATEs op confidence (laag→hoog, zodat twijfelgevallen bovenaan staan)
    # dan CREATEs
    report_df = report_df.sort_values(
        by=["actie", "confidence"],
        ascending=[False, True]
    )

    report_df.to_csv(MATCH_REPORT, index=False)
    print(f"  Opgeslagen: {MATCH_REPORT}")

    # Samenvatting
    updates = report_df[report_df["actie"] == "UPDATE"]
    creates = report_df[report_df["actie"] == "CREATE"]

    print(f"\n  === SAMENVATTING ===")
    print(f"  Totaal records: {len(report_df)}")
    print(f"  UPDATE (match gevonden): {len(updates)}")
    if len(updates) > 0:
        print(f"    Confidence < 75 (twijfelgevallen): {len(updates[updates['confidence'] < 75])}")
        print(f"    Confidence 75-90: {len(updates[(updates['confidence'] >= 75) & (updates['confidence'] < 90)])}")
        print(f"    Confidence >= 90: {len(updates[updates['confidence'] >= 90])}")
    print(f"  CREATE (geen match): {len(creates)}")

    # Toon twijfelgevallen
    borderline = updates[updates["confidence"] < 80].head(15)
    if len(borderline) > 0:
        print(f"\n  === TWIJFELGEVALLEN (confidence < 80) ===")
        for _, row in borderline.iterrows():
            print(f"    {row['cyclowax_naam']:<40} → {row['match_store_naam']:<40} ({row['confidence']}%)")

    # Toon top matches
    top = updates[updates["confidence"] >= 90].head(10)
    if len(top) > 0:
        print(f"\n  === TOP MATCHES (confidence >= 90) ===")
        for _, row in top.iterrows():
            print(f"    {row['cyclowax_naam']:<40} → {row['match_store_naam']:<40} ({row['confidence']}%)")

    # Landen verdeling voor CREATEs
    if len(creates) > 0:
        print(f"\n  === NIEUWE STORES PER LAND ===")
        print(f"    {creates['land'].value_counts().to_dict()}")


if __name__ == "__main__":
    print("Cyclowax klantenbestand → match-rapport\n")

    # Stap 1
    clean_df = parse_xlsx()

    # Stap 2
    stores_df = load_existing_stores()
    print(f"\n  Bestaande stores in DB: {len(stores_df)}")
    report_df = fuzzy_match(clean_df, stores_df)

    # Stap 3
    generate_report(report_df)

    print(f"\n{'=' * 60}")
    print("KLAAR! Review het rapport:")
    print(f"  {MATCH_REPORT}")
    print("Pas de 'actie' kolom aan waar nodig, dan importeren we.")
