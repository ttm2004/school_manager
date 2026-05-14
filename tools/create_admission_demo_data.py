import argparse
import csv
import random
import sys
import unicodedata
from datetime import date, timedelta

HEADERS = [
    "full_name", "gender", "birthday", "citizen_id", "email", "phone", "address",
    "high_school", "graduation_year", "major_code", "method_id",
    "math_score", "literature_score", "english_score", "status",
]

LAST_NAMES = [
    "Nguyễn", "Trần", "Lê", "Phạm", "Hoàng", "Huỳnh", "Phan", "Vũ", "Võ",
    "Đặng", "Bùi", "Đỗ", "Hồ", "Ngô", "Dương",
]
MIDDLE_NAMES = [
    "Văn", "Thị", "Minh", "Quang", "Anh", "Gia", "Hoài", "Thanh",
    "Khánh", "Tuấn", "Ngọc", "Trọng", "Hữu", "Phương",
]
FIRST_NAMES = [
    "An", "Bình", "Châu", "Duy", "Đạt", "Giang", "Hà", "Hải", "Hân",
    "Hùng", "Khánh", "Linh", "Long", "Mai", "Nam", "Ngân", "Nhi",
    "Phong", "Phúc", "Quân", "Tâm", "Thảo", "Trang", "Trí", "Vy",
]

PROVINCES = [
    "Bình Dương", "TP Hồ Chí Minh", "Đồng Nai", "Tây Ninh", "Bình Phước",
    "Long An", "Tiền Giang", "Bà Rịa - Vũng Tàu", "Cần Thơ", "An Giang",
]

HIGH_SCHOOLS = [
    "THPT Võ Minh Đức", "THPT Trịnh Hoài Đức", "THPT Bình Phú", "THPT Nguyễn Trãi",
    "THPT chuyên Hùng Vương", "THPT Dĩ An", "THPT Tân Phước Khánh",
    "THPT Bến Cát", "THPT Lái Thiêu", "THPT Phước Vĩnh",
]

MAJOR_ALIASES = {
    "CNTT": "7480201",
    "CONGNGHETHONGTIN": "7480201",
    "KETOAN": "7340301",
    "KE_TOAN": "7340301",
}
MAJOR_CODES = ["7480201", "7340301"]
STATUSES = ["new", "pending", "approved"]


def random_birthday(graduation_year: int) -> str:
    # Thí sinh tốt nghiệp THPT thường sinh khoảng 18 tuổi.
    year = graduation_year - 18
    start = date(year, 1, 1)
    return (start + timedelta(days=random.randint(0, 364))).isoformat()


def normalize_email_name(full_name: str) -> str:
    normalized = unicodedata.normalize("NFD", full_name.lower())
    without_marks = "".join(ch for ch in normalized if unicodedata.category(ch) != "Mn")
    without_marks = without_marks.replace("đ", "d")
    return "".join(ch for ch in without_marks if ch.isalnum())


def make_record(i: int, graduation_year: int, major_codes: list[str]) -> dict:
    gender = random.choice(["Nam", "Nữ"])
    middle = random.choice(MIDDLE_NAMES)
    if gender == "Nữ" and random.random() < 0.45:
        middle = "Thị"
    if gender == "Nam" and random.random() < 0.35:
        middle = "Văn"

    full_name = f"{random.choice(LAST_NAMES)} {middle} {random.choice(FIRST_NAMES)}"
    email_name = normalize_email_name(full_name)
    unique = f"{graduation_year}{i:04d}"

    math = round(random.uniform(5.0, 9.8), 2)
    literature = round(random.uniform(5.0, 9.5), 2)
    english = round(random.uniform(4.5, 9.7), 2)

    return {
        "full_name": full_name,
        "gender": gender,
        "birthday": random_birthday(graduation_year),
        "citizen_id": f"0{random.randint(10000000000, 99999999999)}",
        "email": f"{email_name}.{unique}@demo.tdmu.edu.vn",
        "phone": f"09{random.randint(10000000, 99999999)}",
        "address": random.choice(PROVINCES),
        "high_school": random.choice(HIGH_SCHOOLS),
        "graduation_year": graduation_year,
        "major_code": random.choice(major_codes),
        "method_id": random.choice([1, 1, 1, 2, 3]),
        "math_score": math,
        "literature_score": literature,
        "english_score": english,
        "status": random.choice(STATUSES),
    }


def create_csv(
    output: str,
    rows: int,
    graduation_year: int,
    seed: int | None = None,
    major_codes: list[str] | None = None,
) -> None:
    if seed is not None:
        random.seed(seed)

    selected_major_codes = []
    for code in (major_codes or MAJOR_CODES):
        normalized = code.strip().upper()
        if not normalized:
            continue
        selected_major_codes.append(MAJOR_ALIASES.get(normalized, normalized))
    if not selected_major_codes:
        selected_major_codes = MAJOR_CODES
    selected_major_codes = selected_major_codes[:2]

    with open(output, "w", newline="", encoding="utf-8-sig") as f:
        writer = csv.DictWriter(f, fieldnames=HEADERS)
        writer.writeheader()
        for i in range(1, rows + 1):
            writer.writerow(make_record(i, graduation_year, selected_major_codes))


if __name__ == "__main__":
    if hasattr(sys.stdout, "reconfigure"):
        sys.stdout.reconfigure(encoding="utf-8")

    parser = argparse.ArgumentParser(description="Tạo CSV hồ sơ tuyển sinh demo/test.")
    parser.add_argument("-o", "--output", default="admission_demo.csv", help="Tên file CSV output")
    parser.add_argument("-n", "--rows", type=int, default=100, help="Số lượng hồ sơ cần tạo")
    parser.add_argument("-y", "--graduation-year", type=int, default=2026, help="Năm tốt nghiệp THPT")
    parser.add_argument(
        "--majors",
        default=",".join(MAJOR_CODES),
        help="Mã ngành demo, cách nhau bằng dấu phẩy; chỉ lấy tối đa 2 ngành",
    )
    parser.add_argument("--seed", type=int, default=None, help="Seed random để tái tạo dữ liệu khi cần")
    args = parser.parse_args()

    majors = [code for code in args.majors.split(",") if code.strip()]
    create_csv(args.output, args.rows, args.graduation_year, args.seed, majors)
    print(f"Đã tạo {args.rows} hồ sơ demo: {args.output}")
