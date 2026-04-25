
const province = document.getElementById("province");
const district = document.getElementById("district");
const ward = document.getElementById("ward");


// LOAD TỈNH
fetch("https://provinces.open-api.vn/api/p/")
    .then(res => res.json())
    .then(data => {

        let html = '<option value="">Chọn tỉnh/thành phố</option>';

        data.forEach(p => {
            html += `<option value="${p.code}">${p.name}</option>`;
        });

        province.innerHTML = html;

    })
    .catch(err => console.error("Lỗi load tỉnh:", err));



// CHỌN TỈNH → LOAD HUYỆN
province.addEventListener("change", function () {

    let provinceCode = this.value;

    district.innerHTML = '<option value="">Đang tải...</option>';
    ward.innerHTML = '<option value="">Chọn xã/phường</option>';

    ward.disabled = true;

    if (!provinceCode) {
        district.disabled = true;
        return;
    }

    fetch(`https://provinces.open-api.vn/api/p/${provinceCode}?depth=2`)
        .then(res => res.json())
        .then(data => {

            let html = '<option value="">Chọn quận/huyện</option>';

            data.districts.forEach(d => {
                html += `<option value="${d.code}">${d.name}</option>`;
            });

            district.innerHTML = html;

            district.disabled = false;

        })
        .catch(err => {
            console.error("Lỗi load huyện:", err);
        });

});



// CHỌN HUYỆN → LOAD XÃ
district.addEventListener("change", function () {

    let districtCode = this.value;

    ward.innerHTML = '<option value="">Đang tải...</option>';

    if (!districtCode) {
        ward.disabled = true;
        return;
    }

    fetch(`https://provinces.open-api.vn/api/d/${districtCode}?depth=2`)
        .then(res => res.json())
        .then(data => {

            let html = '<option value="">Chọn xã/phường</option>';

            data.wards.forEach(w => {
                html += `<option value="${w.code}">${w.name}</option>`;
            });

            ward.innerHTML = html;

            ward.disabled = false;

        })
        .catch(err => {
            console.error("Lỗi load xã:", err);
        });

});
