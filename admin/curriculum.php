<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireRole('admin');
$pageTitle = 'Chuong trinh dao tao';
foreach (["semester_order TINYINT NOT NULL DEFAULT 1","theory_periods INT DEFAULT 30","practice_periods INT DEFAULT 0","is_mandatory TINYINT(1) DEFAULT 1"] as $col) {
    $cn = explode(' ',$col)[0];
    $chk = $conn->query("SHOW COLUMNS FROM subjects LIKE '$cn'");
    if ($chk && $chk->num_rows==0) $conn->query("ALTER TABLE subjects ADD COLUMN $col");
}
$success = $error = '';
$filter_major = intval($_GET['major_id'] ?? 0);
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $action = $_POST['action'] ?? '';
    if ($action==='add') {
        $mid=intval($_POST['major_id']??0); $code=trim($_POST['subject_code']??''); $name=trim($_POST['subject_name']??'');
        $cr=intval($_POST['credits']??3); $type=trim($_POST['subject_type_new']??'required'); $sem=intval($_POST['semester_order']??1);
        $lt=intval($_POST['theory_periods']??30); $th=intval($_POST['practice_periods']??0); $desc=trim($_POST['description']??''); $mand=intval($_POST['is_mandatory']??1);
        if ($mid && $code && $name) {
            $st=$conn->prepare("INSERT INTO subjects (major_id,subject_code,subject_name,credits,subject_type_new,semester_order,theory_periods,practice_periods,is_mandatory,description) VALUES (?,?,?,?,?,?,?,?,?,?)");
            $st->bind_param('issisisisi',$mid,$code,$name,$cr,$type,$sem,$lt,$th,$mand,$desc);
            $st->execute() ? $success='Them mon hoc thanh cong!' : $error='Loi: '.$conn->error; $st->close();
        } else { $error='Vui long dien day du.'; }
    }
    if ($action==='edit') {
        $id=intval($_POST['id']??0); $mid=intval($_POST['major_id']??0); $code=trim($_POST['subject_code']??''); $name=trim($_POST['subject_name']??'');
        $cr=intval($_POST['credits']??3); $type=trim($_POST['subject_type_new']??'required'); $sem=intval($_POST['semester_order']??1);
        $lt=intval($_POST['theory_periods']??30); $th=intval($_POST['practice_periods']??0); $desc=trim($_POST['description']??''); $mand=intval($_POST['is_mandatory']??1);
        if ($id && $name) {
            $st=$conn->prepare("UPDATE subjects SET major_id=?,subject_code=?,subject_name=?,credits=?,subject_type_new=?,semester_order=?,theory_periods=?,practice_periods=?,is_mandatory=?,description=? WHERE id=?");
            $st->bind_param('issisisisi i',$mid,$code,$name,$cr,$type,$sem,$lt,$th,$mand,$desc,$id);
            $st->execute() ? $success='Cap nhat thanh cong!' : $error='Loi: '.$conn->error; $st->close();
        }
    }
    if ($action==='delete') {
        $id=intval($_POST['id']??0);
        if ($id) { $st=$conn->prepare("DELETE FROM subjects WHERE id=?"); $st->bind_param('i',$id); $st->execute() ? $success='Xoa thanh cong!' : $error='Loi: '.$conn->error; $st->close(); }
    }
    if ($action==='seed') {
        $sid=intval($_POST['seed_major_id']??0);
        if ($sid) {
            $mc=$conn->query("SELECT major_code FROM majors WHERE id=$sid")->fetch_assoc()['major_code']??'';
            $conn->query("DELETE FROM subjects WHERE major_id=$sid");
            $rows=getSeedData($sid,$mc); $cnt=0;
            foreach ($rows as $r) {
                $st=$conn->prepare("INSERT INTO subjects (major_id,subject_code,subject_name,credits,subject_type_new,semester_order,theory_periods,practice_periods,is_mandatory,description) VALUES (?,?,?,?,?,?,?,?,?,?)");
                $st->bind_param('issisisisi',$r[0],$r[1],$r[2],$r[3],$r[4],$r[5],$r[6],$r[7],$r[8],$r[9]);
                if($st->execute()) $cnt++; $st->close();
            }
            $success="Da seed $cnt mon hoc!";
        }
    }
    if ($filter_major) { header("Location: ?major_id=$filter_major"); exit; }
    else { header("Location: curriculum.php"); exit; }
}

function getSeedData($mid,$mc){
    $r=fn($c,$n,$cr,$t,$s,$lt,$th,$mand,$d='')=>[$mid,$c,$n,$cr,$t,$s,$lt,$th,$mand,$d];
    $base=[$r('DC_TTHCM','Tu tuong Ho Chi Minh',2,'general',1,30,0,1),$r('DC_GDQP1','Giao duc quoc phong 1',3,'general',1,45,0,1),$r('DC_GDTC1','Giao duc the chat 1',1,'general',1,15,0,1),$r('DC_TA1','Tieng Anh 1',3,'general',1,30,30,1),$r('DC_TRIET','Triet hoc Mac-Lenin',3,'general',2,45,0,1),$r('DC_KTCT','Kinh te chinh tri',2,'general',2,30,0,1),$r('DC_TA2','Tieng Anh 2',3,'general',2,30,30,1),$r('DC_GDTC2','Giao duc the chat 2',1,'general',2,0,15,1),$r('DC_GDQP2','Giao duc quoc phong 2',2,'general',3,0,30,1),$r('DC_CNXH','Chu nghia xa hoi khoa hoc',2,'general',3,30,0,1),$r('DC_GDTC3','Giao duc the chat 3',2,'general',4,0,30,1),$r('DC_LICHSU','Lich su Dang CSVN',2,'general',4,30,0,1),$r('DC_THUCHANH','Thuc hanh nghe nghiep',3,'required',6,0,90,1),$r('DC_TTDN','Thuc tap doanh nghiep',6,'required',7,0,180,1),$r('DC_CHUYENDE_TN','Chuyen de tot nghiep',3,'elective',7,0,90,0),$r('DC_KLTN','Khoa luan tot nghiep',6,'required',8,0,180,1)];
    $spec=[];
    if(in_array($mc,['7480201','7480104'])){$spec=[$r('TOAN1','Toan cao cap 1',3,'required',1,45,0,1),$r('XSTK','Xac suat thong ke',3,'required',1,45,0,1),$r('LTLT','Lap trinh can ban',3,'required',1,30,30,1),$r('TOAN2','Toan roi rac',3,'required',2,45,0,1),$r('CTDL','Cau truc du lieu va giai thuat',3,'required',2,45,0,1),$r('LTHDT','Lap trinh huong doi tuong',3,'required',2,30,30,1),$r('KTMT','Kien truc may tinh',3,'required',2,45,0,1),$r('MANG','Mang may tinh',3,'required',2,45,0,1),$r('CSDL','Co so du lieu',3,'required',3,30,30,1),$r('HDD','He dieu hanh',3,'required',3,30,30,1),$r('HTTT','He thong thong tin',3,'required',3,45,0,1),$r('TA_CN','Tieng Anh chuyen nganh CNTT',2,'required',3,30,0,1),$r('PTTKHT','Phan tich thiet ke he thong',3,'required',4,30,30,1),$r('LTJAVA','Lap trinh Java',3,'required',4,30,30,1),$r('ANTT','An toan thong tin',3,'required',4,30,30,1),$r('LTMOBILE','Lap trinh di dong',3,'required',4,30,30,1),$r('LTWEBFE','Lap trinh Web Frontend',3,'required',5,30,30,1),$r('LTWEBBE','Lap trinh Web Backend',3,'required',5,30,30,1),$r('KPDL','Khai pha du lieu',3,'required',5,45,0,1),$r('TTNT','Tri tue nhan tao',3,'required',5,45,0,1),$r('QLDA','Quan ly du an phan mem',2,'required',5,30,0,1),$r('CLOUD','Dien toan dam may',3,'required',6,30,30,1),$r('BIGDATA','Du lieu lon',3,'required',6,30,30,1),$r('CD1','Chuyen de CNTT 1',3,'elective',6,30,30,0),$r('CD2','Chuyen de CNTT 2',3,'elective',6,30,30,0)];}
    elseif($mc==='7480103'){$spec=[$r('TOAN1','Toan cao cap',3,'required',1,45,0,1),$r('XSTK','Xac suat thong ke',3,'required',1,45,0,1),$r('LTC','Lap trinh C',3,'required',1,30,30,1),$r('TOAN2','Toan roi rac',3,'required',1,45,0,1),$r('CTDL','Cau truc du lieu va giai thuat',3,'required',2,45,0,1),$r('OOP','Lap trinh huong doi tuong',3,'required',2,30,30,1),$r('KTMT','Kien truc may tinh',3,'required',2,45,0,1),$r('MANG','Mang may tinh',3,'required',2,45,0,1),$r('CSDL','Co so du lieu',3,'required',3,30,30,1),$r('PTTKHT','Phan tich thiet ke he thong',3,'required',3,30,30,1),$r('HDD','He dieu hanh',3,'required',3,30,30,1),$r('KYTHUAT','Ky thuat lap trinh',2,'required',3,30,0,1),$r('CNPM','Cong nghe phan mem',3,'required',4,45,0,1),$r('KIEMTHU','Kiem thu phan mem',3,'required',4,30,30,1),$r('TA_CN','Tieng Anh chuyen nganh',2,'required',4,30,0,1),$r('WEBFE','Lap trinh Web Frontend',3,'required',4,30,30,1),$r('WEBBE','Lap trinh Web Backend',3,'required',4,30,30,1),$r('MOBILE','Lap trinh di dong',2,'required',4,15,30,1),$r('DEVOPS','DevOps va CI/CD',3,'required',5,30,30,1),$r('CLOUD','Dien toan dam may',3,'required',5,30,30,1),$r('ANTT','An toan phan mem',3,'required',5,30,30,1),$r('QLDA','Quan ly du an phan mem',3,'required',5,45,0,1),$r('CD1','Chuyen de KTPM 1',2,'elective',5,30,0,0),$r('MICROSERVICE','Kien truc Microservices',3,'required',6,30,30,1),$r('AI_APP','Ung dung AI trong phan mem',3,'required',6,30,30,1),$r('CD2','Chuyen de KTPM 2',3,'elective',6,30,30,0),$r('CD3','Chuyen de KTPM 3',3,'elective',6,30,30,0)];}
    elseif($mc==='7340301'){$spec=[$r('TOAN1','Toan cao cap',3,'required',1,45,0,1),$r('PHAP','Phap luat dai cuong',3,'required',1,45,0,1),$r('KTVM','Kinh te vi mo',3,'required',2,45,0,1),$r('KTVMO','Kinh te vi mo',3,'required',2,45,0,1),$r('TAICHINH','Tai chinh tien te',3,'required',2,45,0,1),$r('THONGKE','Thong ke kinh te',3,'required',2,45,0,1),$r('KTDC','Ke toan dai cuong',3,'required',3,30,30,1),$r('TCKT','Tai chinh ke toan',3,'required',3,30,30,1),$r('TA_CN','Tieng Anh chuyen nganh ke toan',3,'required',3,45,0,1),$r('PHAPKT','Phap luat ke toan',3,'required',3,45,0,1),$r('KTDN','Ke toan doanh nghiep',3,'required',4,30,30,1),$r('KTTC','Ke toan tai chinh',3,'required',4,30,30,1),$r('KTQT','Ke toan quan tri',3,'required',4,30,30,1),$r('THUE','Thue',3,'required',4,45,0,1),$r('EXCEL_KT','Tin hoc ke toan',2,'required',4,15,30,1),$r('TAICHINH2','Phan tich tai chinh',2,'required',4,30,0,1),$r('KTNH','Ke toan ngan hang',3,'required',5,30,30,1),$r('KTNN','Ke toan nha nuoc',3,'required',5,30,30,1),$r('KTQT2','Ke toan quan tri nang cao',3,'required',5,30,30,1),$r('KIEMTOAN','Kiem toan',3,'required',5,30,30,1),$r('PHANMEM','Phan mem ke toan',3,'required',5,15,45,1),$r('CD1','Chuyen de ke toan 1',3,'elective',5,30,30,0),$r('KIEMTOAN2','Kiem toan nang cao',3,'required',6,30,30,1),$r('NGHIEPVU','Nghiep vu ke toan tong hop',3,'required',6,15,60,1),$r('CD2','Chuyen de ke toan 2',3,'elective',6,30,30,0),$r('CD3','Chuyen de ke toan 3',3,'elective',6,30,30,0)];}
    elseif($mc==='7340101'){$spec=[$r('TOAN1','Toan cao cap',3,'required',1,45,0,1),$r('PHAP','Phap luat dai cuong',3,'required',1,45,0,1),$r('KTVM','Kinh te vi mo',3,'required',2,45,0,1),$r('KTVMO','Kinh te vi mo',3,'required',2,45,0,1),$r('THONGKE','Thong ke kinh doanh',3,'required',2,45,0,1),$r('KTDC','Ke toan dai cuong',3,'required',2,30,30,1),$r('QTDN','Quan tri doanh nghiep',3,'required',3,45,0,1),$r('MARKETING','Marketing can ban',3,'required',3,45,0,1),$r('TAICHINH','Tai chinh doanh nghiep',3,'required',3,45,0,1),$r('TA_CN','Tieng Anh kinh doanh',3,'required',3,45,0,1),$r('QTNS','Quan tri nhan su',3,'required',4,45,0,1),$r('QTCL','Quan tri chien luoc',3,'required',4,45,0,1),$r('QTSX','Quan tri san xuat',3,'required',4,45,0,1),$r('THUONG','Luat thuong mai',3,'required',4,45,0,1),$r('ECOM','Thuong mai dien tu',2,'required',4,30,0,1),$r('TINHOC','Tin hoc ung dung',2,'required',4,15,30,1),$r('QTDA','Quan tri du an',3,'required',5,45,0,1),$r('KHOINGHIEP','Khoi nghiep kinh doanh',3,'required',5,30,30,1),$r('QTQT','Quan tri quoc te',3,'required',5,45,0,1),$r('CD1','Chuyen de QTKD 1',3,'elective',5,30,30,0),$r('CD2','Chuyen de QTKD 2',3,'elective',5,30,30,0),$r('NGHIEPVU','Nghiep vu kinh doanh',3,'required',5,15,60,1),$r('PHANTICH','Phan tich kinh doanh',3,'required',6,30,30,1),$r('QTCL2','Quan tri chien luoc nang cao',3,'required',6,30,30,1),$r('CD3','Chuyen de QTKD 3',3,'elective',6,30,30,0),$r('CD4','Chuyen de QTKD 4',3,'elective',6,30,30,0)];}
    elseif($mc==='7380101'){$spec=[$r('NHANUOC','Nha nuoc va phap luat dai cuong',3,'required',1,45,0,1),$r('HIENPHAP','Luat Hien phap',3,'required',1,45,0,1),$r('HANH','Luat Hanh chinh',3,'required',2,45,0,1),$r('HINH','Luat Hinh su',3,'required',2,45,0,1),$r('DAN','Luat Dan su',3,'required',2,45,0,1),$r('TOTHUNG_HS','Luat To tung hinh su',3,'required',3,45,0,1),$r('TOTUNGDS','Luat To tung dan su',3,'required',3,45,0,1),$r('THUONG','Luat Thuong mai',3,'required',3,45,0,1),$r('TA_CN','Tieng Anh phap ly',3,'required',3,45,0,1),$r('LAODONG','Luat Lao dong',2,'required',3,30,0,1),$r('HONNHAN','Luat Hon nhan gia dinh',3,'required',4,45,0,1),$r('DATDAI','Luat Dat dai',3,'required',4,45,0,1),$r('DOANHNGHIEP','Luat Doanh nghiep',3,'required',4,45,0,1),$r('QUOCTE','Cong phap quoc te',3,'required',4,45,0,1),$r('TPQUOCTE','Tu phap quoc te',3,'required',4,45,0,1),$r('THUE','Luat Thue',2,'required',4,30,0,1),$r('DAUTU','Luat Dau tu',3,'required',5,45,0,1),$r('SOHUU','Luat So huu tri tue',3,'required',5,45,0,1),$r('MTRG','Luat Moi truong',3,'required',5,45,0,1),$r('KYNANGLUAT','Ky nang hanh nghe luat',3,'required',5,30,30,1),$r('CD1','Chuyen de Luat 1',3,'elective',5,30,30,0),$r('CD2','Chuyen de Luat 2',3,'elective',5,30,30,0),$r('TOANPHAP','Toa an va to tung thuc hanh',3,'required',6,15,60,1),$r('PHAPLUAT_KD','Phap luat kinh doanh nang cao',3,'required',6,30,30,1),$r('CD3','Chuyen de Luat 3',3,'elective',6,30,30,0),$r('CD4','Chuyen de Luat 4',3,'elective',6,30,30,0)];}
    elseif($mc==='7220201'){$spec=[$r('NGHE1','Nghe - Noi 1',3,'required',1,30,30,1),$r('DOC1','Doc - Viet 1',3,'required',1,30,30,1),$r('NGU1','Ngu phap tieng Anh 1',3,'required',1,45,0,1),$r('VANHOA','Van hoa Anh - My',3,'required',2,45,0,1),$r('NGHE2','Nghe - Noi 2',3,'required',2,30,30,1),$r('DOC2','Doc - Viet 2',3,'required',2,30,30,1),$r('NGU2','Ngu phap tieng Anh 2',3,'required',2,45,0,1),$r('NGHE3','Nghe - Noi 3',3,'required',3,30,30,1),$r('DOC3','Doc - Viet 3',3,'required',3,30,30,1),$r('DICH1','Dich thuat 1',3,'required',3,30,30,1),$r('VANHOC','Van hoc Anh',3,'required',3,45,0,1),$r('NGONNGUHOC','Ngon ngu hoc dai cuong',2,'required',3,30,0,1),$r('NGHE4','Nghe - Noi 4',3,'required',4,30,30,1),$r('DICH2','Dich thuat 2',3,'required',4,30,30,1),$r('VIET1','Viet hoc thuat',3,'required',4,30,30,1),$r('THUONGMAI','Tieng Anh thuong mai',3,'required',4,30,30,1),$r('GIAODUC','PP giang day tieng Anh',3,'required',4,30,30,1),$r('BIENPHIEN','Bien phien dich',3,'required',5,30,30,1),$r('CHUYENANH','Tieng Anh chuyen nganh',3,'required',5,30,30,1),$r('VIET2','Viet sang tao',3,'required',5,30,30,1),$r('CD1','Chuyen de NNA 1',3,'elective',5,30,30,0),$r('CD2','Chuyen de NNA 2',3,'elective',5,30,30,0),$r('NGHIEPVU','Nghiep vu ngon ngu',3,'required',5,15,60,1),$r('CD3','Chuyen de NNA 3',3,'elective',6,30,30,0),$r('CD4','Chuyen de NNA 4',3,'elective',6,30,30,0),$r('DICHTHUAT','Dich thuat chuyen nghiep',3,'required',6,30,30,1),$r('GIAOTIEP','Giao tiep lien van hoa',3,'required',6,30,30,1)];}
    elseif($mc==='7140202'){$spec=[$r('TAMLY','Tam ly hoc dai cuong',3,'required',1,45,0,1),$r('GIAODUC','Giao duc hoc dai cuong',3,'required',1,45,0,1),$r('TAMLYTRE','Tam ly hoc tre em',3,'required',2,45,0,1),$r('GIAODUC2','Giao duc hoc tieu hoc',3,'required',2,45,0,1),$r('TINHOC','Tin hoc ung dung',3,'required',2,15,60,1),$r('PPDAY_TOAN','PP day hoc Toan tieu hoc',3,'required',3,30,30,1),$r('PPDAY_TV','PP day hoc Tieng Viet',3,'required',3,30,30,1),$r('PPDAY_TN','PP day hoc Tu nhien - Xa hoi',3,'required',3,30,30,1),$r('AMNHAC','Am nhac va PP day hoc',3,'required',3,30,30,1),$r('MYTHUAT','My thuat va PP day hoc',2,'required',3,15,30,1),$r('PPDAY_ANH','PP day hoc Tieng Anh TH',3,'required',4,30,30,1),$r('PPDAY_TD','PP day hoc The duc TH',3,'required',4,15,45,1),$r('DANH','Danh gia trong giao duc',3,'required',4,45,0,1),$r('QUANLY','Quan ly lop hoc',3,'required',4,30,30,1),$r('CD1','Chuyen de GD tieu hoc 1',3,'elective',4,30,30,0),$r('CD2','Chuyen de GD tieu hoc 2',3,'elective',4,30,30,0),$r('GIAODUC3','Giao duc dac biet',3,'required',5,30,30,1),$r('CONGNGHE','Cong nghe day hoc',3,'required',5,15,60,1),$r('CD3','Chuyen de GD tieu hoc 3',3,'elective',5,30,30,0),$r('CD4','Chuyen de GD tieu hoc 4',3,'elective',5,30,30,0),$r('NGHIEPVU','Nghiep vu su pham',3,'required',5,15,60,1),$r('TUVANTL','Tu van tam ly hoc duong',3,'required',5,30,30,1),$r('CD5','Chuyen de GD tieu hoc 5',3,'elective',6,30,30,0),$r('CD6','Chuyen de GD tieu hoc 6',3,'elective',6,30,30,0),$r('NGHIENCUU','Nghien cuu khoa hoc GD',3,'required',6,30,30,1),$r('GIAODUC4','Giao duc gia dinh - cong dong',3,'required',6,30,30,1)];}
    else{$spec=[$r('TOAN1','Toan cao cap',3,'required',1,45,0,1),$r('PHAP','Phap luat dai cuong',3,'required',1,45,0,1),$r('NHAP_MON','Nhap mon nganh hoc',3,'required',1,45,0,1),$r('CO_SO_1','Co so nganh 1',3,'required',2,45,0,1),$r('CO_SO_2','Co so nganh 2',3,'required',2,45,0,1),$r('CO_SO_3','Co so nganh 3',3,'required',2,45,0,1),$r('TA_CN','Tieng Anh chuyen nganh',3,'required',3,45,0,1),$r('CHUYEN_1','Chuyen nganh 1',3,'required',3,45,0,1),$r('CHUYEN_2','Chuyen nganh 2',3,'required',3,45,0,1),$r('CHUYEN_3','Chuyen nganh 3',3,'required',3,45,0,1),$r('CHUYEN_4','Chuyen nganh 4',3,'required',4,45,0,1),$r('CHUYEN_5','Chuyen nganh 5',3,'required',4,45,0,1),$r('CHUYEN_6','Chuyen nganh 6',3,'required',4,45,0,1),$r('CHUYEN_7','Chuyen nganh 7',3,'required',4,45,0,1),$r('CD1','Chuyen de 1',3,'elective',5,30,30,0),$r('CD2','Chuyen de 2',3,'elective',5,30,30,0),$r('CD3','Chuyen de 3',3,'elective',5,30,30,0),$r('CHUYEN_8','Chuyen nganh 8',3,'required',5,45,0,1),$r('CHUYEN_9','Chuyen nganh 9',3,'required',5,45,0,1),$r('CHUYEN_10','Chuyen nganh 10',3,'required',5,45,0,1),$r('CD4','Chuyen de 4',3,'elective',6,30,30,0),$r('CD5','Chuyen de 5',3,'elective',6,30,30,0),$r('CHUYEN_11','Chuyen nganh 11',3,'required',6,45,0,1),$r('CHUYEN_12','Chuyen nganh 12',3,'required',6,45,0,1)];}
    return array_merge($base,$spec);
}
$majors=$conn->query("SELECT m.*, f.faculty_name FROM majors m LEFT JOIN faculties f ON m.faculty_id=f.id ORDER BY f.faculty_name, m.major_name");
$majorsArr=[];
while($m=$majors->fetch_assoc()) $majorsArr[]=$m;
$subjects=[];
if($filter_major){
    // Ưu tiên lấy từ bảng curriculum (có year_label, semester_label) nếu có dữ liệu
    $chkCurr = $conn->prepare("SELECT COUNT(*) AS c FROM curriculum WHERE major_id=? AND deleted_at IS NULL");
    $chkCurr->bind_param('i',$filter_major); $chkCurr->execute();
    $hasCurr = (int)($chkCurr->get_result()->fetch_assoc()['c'] ?? 0);
    $chkCurr->close();

    if ($hasCurr > 0) {
        // Lấy từ curriculum JOIN subjects — có đầy đủ year_label, semester_label
        $st=$conn->prepare("
            SELECT s.id, s.major_id, s.subject_code, s.subject_name, s.credits,
                   s.theory_periods, s.practice_periods, s.total_periods,
                   s.subject_type, s.subject_type_new, s.is_mandatory, s.description,
                   c.suggested_semester AS semester_order,
                   c.semester_label, c.year_label,
                   c.subject_type AS curr_type, c.id AS curr_id
            FROM curriculum c
            JOIN subjects s ON c.subject_id = s.id
            WHERE c.major_id=? AND c.deleted_at IS NULL
            ORDER BY c.year_label ASC, c.suggested_semester ASC, s.is_mandatory DESC, s.subject_name ASC
        ");
    } else {
        // Fallback: lấy từ subjects trực tiếp (dữ liệu cũ chưa có curriculum)
        $st=$conn->prepare("
            SELECT *, semester_order, NULL AS semester_label, NULL AS year_label,
                   subject_type_new AS curr_type, id AS curr_id
            FROM subjects WHERE major_id=?
            ORDER BY semester_order ASC, is_mandatory DESC, subject_name ASC
        ");
    }
    $st->bind_param('i',$filter_major); $st->execute();
    $res=$st->get_result(); while($row=$res->fetch_assoc()) $subjects[]=$row; $st->close();
}

// Nhóm theo năm học + học kỳ (key: "year_label|semester_label|semester_order")
$bySemester=[];
foreach($subjects as $s) {
    $yearLabel = $s['year_label'] ?? '';
    $semLabel  = $s['semester_label'] ?? '';
    $semOrder  = (int)($s['semester_order'] ?? 1);
    // Key để sort: year_label + semester_order đảm bảo thứ tự đúng
    $key = ($yearLabel ?: '0000') . '|' . sprintf('%02d', $semOrder) . '|' . $semLabel;
    $bySemester[$key][] = $s;
}
ksort($bySemester);
$totalCredits=array_sum(array_column($subjects,'credits'));
$currentMajor=null;
foreach($majorsArr as $m){ if($m['id']==$filter_major){$currentMajor=$m;break;} }
include 'includes/header.php';
include 'includes/sidebar.php';

?>
<div class="admin-main">
<div class="admin-topbar">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
        <span class="admin-topbar-title"><i class="bi bi-journal-bookmark-fill me-2"></i>Chuong trinh dao tao</span>
    </div>
    <span class="text-muted small"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
</div>
<div class="admin-content">
<?php if($success): ?><div class="alert alert-success auto-dismiss alert-dismissible fade show"><i class="bi bi-check-circle-fill me-2"></i><?php echo $success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if($error): ?><div class="alert alert-danger auto-dismiss alert-dismissible fade show"><i class="bi bi-exclamation-circle-fill me-2"></i><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" class="d-flex gap-3 align-items-end flex-wrap">
            <div style="min-width:320px">
                <label class="form-label small mb-1 fw-bold">Chon nganh dao tao</label>
                <select name="major_id" class="form-select" onchange="this.form.submit()">
                    <option value="">-- Chon nganh --</option>
                    <?php foreach($majorsArr as $m): ?>
                    <option value="<?php echo $m['id']; ?>" <?php echo $filter_major==$m['id']?'selected':''; ?>>
                        <?php echo htmlspecialchars($m['major_code'].' - '.$m['major_name'].' ('.$m['faculty_name'].')'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if($filter_major): ?>
            <button type="button" class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="bi bi-plus-lg me-1"></i>Them mon hoc
            </button>
            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#importModal">
                <i class="bi bi-file-earmark-excel-fill me-1"></i>Import Excel
            </button>
            <form method="POST" class="d-inline" onsubmit="return confirm('Xoa toan bo mon cu va seed lai du lieu mau?')">
                <input type="hidden" name="action" value="seed">
                <input type="hidden" name="seed_major_id" value="<?php echo $filter_major; ?>">
                <button type="submit" class="btn btn-outline-info btn-sm">
                    <i class="bi bi-database-fill-add me-1"></i>Seed du lieu mau
                </button>
            </form>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if(!$filter_major): ?>
<div class="card"><div class="card-body text-center text-muted py-5"><i class="bi bi-journal-bookmark fs-2 d-block mb-2"></i>Chon nganh de xem chuong trinh dao tao</div></div>
<?php else: ?>

<?php if($currentMajor): ?>
<div class="card mb-4 border-0" style="background:linear-gradient(135deg,var(--navy) 0%,#1a5276 100%);color:#fff;">
    <div class="card-body py-3">
        <div class="row align-items-center">
            <div class="col-md-6">
                <div class="d-flex align-items-center gap-3">
                    <div style="width:50px;height:50px;background:rgba(255,255,255,0.15);border-radius:12px;display:flex;align-items:center;justify-content:center;"><i class="bi bi-mortarboard-fill fs-4"></i></div>
                    <div>
                        <div class="fw-bold fs-5"><?php echo htmlspecialchars($currentMajor['major_name']); ?></div>
                        <div style="opacity:.8;font-size:.85rem;">Ma nganh: <?php echo htmlspecialchars($currentMajor['major_code']); ?> | Khoa: <?php echo htmlspecialchars($currentMajor['faculty_name']); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="row g-2 text-center mt-2 mt-md-0">
                    <div class="col-3"><div class="fw-bold fs-5"><?php echo $currentMajor['total_credits']; ?></div><div style="opacity:.7;font-size:.75rem;">TC yêu cầu</div></div>
                    <div class="col-3"><div class="fw-bold fs-5"><?php echo $totalCredits; ?></div><div style="opacity:.7;font-size:.75rem;">TC đã có</div></div>
                    <div class="col-3"><div class="fw-bold fs-5"><?php echo count($subjects); ?></div><div style="opacity:.7;font-size:.75rem;">Môn học</div></div>
                    <div class="col-3"><div class="fw-bold fs-5"><?php echo count($bySemester); ?></div><div style="opacity:.7;font-size:.75rem;">Học kỳ</div></div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if(empty($subjects)): ?>
<div class="card">
    <div class="card-body text-center text-muted py-5">
        <i class="bi bi-inbox fs-2 d-block mb-2"></i>Nganh nay chua co mon hoc nao.
        <div class="mt-3 d-flex gap-2 justify-content-center">
            <button class="btn btn-gold" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-plus-lg me-1"></i>Them mon hoc</button>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#importModal"><i class="bi bi-file-earmark-excel-fill me-1"></i>Import Excel</button>
            <form method="POST" class="d-inline">
                <input type="hidden" name="action" value="seed">
                <input type="hidden" name="seed_major_id" value="<?php echo $filter_major; ?>">
                <button type="submit" class="btn btn-outline-info"><i class="bi bi-database-fill-add me-1"></i>Seed du lieu mau (~120TC)</button>
            </form>
        </div>
    </div>
</div>
<?php else: ?>
<?php
$typeMap=['required'=>['Bắt buộc','danger'],'elective'=>['Tự chọn','warning'],'general'=>['Đại cương','info'],
          'bat buoc'=>['Bắt buộc','danger'],'tu chon'=>['Tự chọn','warning'],
          'Bắt buộc'=>['Bắt buộc','danger'],'Tự chọn'=>['Tự chọn','warning']];
$prevYear = null;
foreach($bySemester as $key=>$semSubjects):
    [$yearLabel, $semOrderPad, $semLabel] = explode('|', $key);
    $semOrder = (int)$semOrderPad;
    $semCredits=array_sum(array_column($semSubjects,'credits'));
    $semTotalPeriods=array_sum(array_column($semSubjects,'total_periods'));
    // In header năm học khi đổi năm
    if ($yearLabel && $yearLabel !== $prevYear):
        $prevYear = $yearLabel;
?>
<div class="d-flex align-items-center gap-2 mb-2 mt-3">
    <i class="bi bi-calendar-range-fill text-navy"></i>
    <strong class="text-navy fs-6">Năm học <?php echo htmlspecialchars($yearLabel); ?></strong>
    <hr class="flex-grow-1 my-0">
</div>
<?php endif; ?>
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>
            <i class="bi bi-calendar3 me-2"></i>
            <strong><?php echo htmlspecialchars($semLabel ?: 'Học kỳ '.$semOrder); ?></strong>
            <?php if($yearLabel): ?><span class="text-muted ms-2 small"><?php echo htmlspecialchars($yearLabel); ?></span><?php endif; ?>
        </span>
        <div class="d-flex gap-2 align-items-center">
            <span class="badge bg-light text-dark border"><?php echo $semTotalPeriods; ?> tiết</span>
            <span class="badge bg-navy"><?php echo $semCredits; ?> TC | <?php echo count($semSubjects); ?> môn</span>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle" style="font-size:.88rem;">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Mã môn</th>
                        <th>Tên môn học</th>
                        <th class="text-center" style="width:50px">TC</th>
                        <th class="text-center" style="width:55px">LT</th>
                        <th class="text-center" style="width:55px">TH</th>
                        <th class="text-center" style="width:65px">Tổng tiết</th>
                        <th style="width:90px">Loại môn</th>
                        <th style="width:80px">Bắt buộc</th>
                        <th style="width:80px">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                <?php $idx=1; foreach($semSubjects as $sub):
                    // Ưu tiên loại từ curriculum, fallback về subjects
                    $t = $sub['curr_type'] ?? $sub['subject_type_new'] ?? $sub['subject_type'] ?? 'required';
                    $type = $typeMap[$t] ?? ['Khác','secondary'];
                    $lt  = (int)($sub['theory_periods'] ?? 0);
                    $th  = (int)($sub['practice_periods'] ?? 0);
                    $tot = (int)($sub['total_periods'] ?? ($lt + $th));
                    $isMandatory = (bool)($sub['is_mandatory'] ?? 1);
                ?>
                <tr>
                    <td class="text-muted"><?php echo $idx++; ?></td>
                    <td><span class="badge bg-navy"><?php echo htmlspecialchars($sub['subject_code']); ?></span></td>
                    <td class="fw-semibold"><?php echo htmlspecialchars($sub['subject_name']); ?></td>
                    <td class="text-center"><span class="badge bg-gold text-dark fw-bold"><?php echo $sub['credits']; ?></span></td>
                    <td class="text-center text-muted"><?php echo $lt ?: '-'; ?></td>
                    <td class="text-center text-muted"><?php echo $th ?: '-'; ?></td>
                    <td class="text-center text-muted"><?php echo $tot ?: '-'; ?></td>
                    <td><span class="badge bg-<?php echo $type[1]; ?>"><?php echo $type[0]; ?></span></td>
                    <td class="text-center">
                        <?php if($isMandatory): ?>
                        <span class="badge bg-success">Có</span>
                        <?php else: ?>
                        <span class="badge bg-secondary">Không</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#editModal"
                            data-id="<?php echo $sub['id']; ?>"
                            data-major="<?php echo $sub['major_id']; ?>"
                            data-code="<?php echo htmlspecialchars($sub['subject_code']); ?>"
                            data-name="<?php echo htmlspecialchars($sub['subject_name']); ?>"
                            data-credits="<?php echo $sub['credits']; ?>"
                            data-type="<?php echo htmlspecialchars($t); ?>"
                            data-sem="<?php echo $semOrder; ?>"
                            data-lt="<?php echo $lt; ?>"
                            data-th="<?php echo $th; ?>"
                            data-desc="<?php echo htmlspecialchars($sub['description'] ?? ''); ?>">
                            <i class="bi bi-pencil-fill"></i>
                        </button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Xóa môn học này?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $sub['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash-fill"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <td colspan="3" class="text-end fw-bold text-muted small">Tổng học kỳ:</td>
                        <td class="text-center"><span class="badge bg-navy fw-bold"><?php echo $semCredits; ?></span></td>
                        <td colspan="2"></td>
                        <td class="text-center text-muted small"><?php echo $semTotalPeriods; ?></td>
                        <td colspan="3"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>
<?php endif; ?>
</div>
<div class="admin-footer">&copy; <?php echo date('Y'); ?> TDMU</div>
</div>

<!-- MODAL THEM -->
<div class="modal fade" id="addModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><i class="bi bi-journal-plus me-2"></i>Them mon hoc</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST"><input type="hidden" name="action" value="add">
    <div class="modal-body"><div class="row g-3">
        <div class="col-12"><label class="form-label">Nganh <span class="text-danger">*</span></label><select name="major_id" class="form-select" required><?php foreach($majorsArr as $m): ?><option value="<?php echo $m['id']; ?>" <?php echo $filter_major==$m['id']?'selected':''; ?>><?php echo htmlspecialchars($m['major_name']); ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label">Ma mon <span class="text-danger">*</span></label><input type="text" name="subject_code" class="form-control" required></div>
        <div class="col-md-8"><label class="form-label">Ten mon <span class="text-danger">*</span></label><input type="text" name="subject_name" class="form-control" required></div>
        <div class="col-md-2"><label class="form-label">Tin chi</label><input type="number" name="credits" class="form-control" value="3" min="1" max="10"></div>
        <div class="col-md-2"><label class="form-label">Tiet LT</label><input type="number" name="theory_periods" class="form-control" value="30" min="0"></div>
        <div class="col-md-2"><label class="form-label">Tiet TH</label><input type="number" name="practice_periods" class="form-control" value="0" min="0"></div>
        <div class="col-md-3"><label class="form-label">Loai mon</label><select name="subject_type_new" class="form-select"><option value="required">Bat buoc</option><option value="elective">Tu chon</option><option value="general">Dai cuong</option></select></div>
        <div class="col-md-3"><label class="form-label">Hoc ky</label><select name="semester_order" class="form-select"><?php for($i=1;$i<=11;$i++): ?><option value="<?php echo $i; ?>">Hoc ky <?php echo $i; ?></option><?php endfor; ?></select></div>
        <div class="col-12"><label class="form-label">Mo ta</label><textarea name="description" class="form-control" rows="2"></textarea></div>
    </div></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Huy</button><button type="submit" class="btn btn-gold"><i class="bi bi-save me-1"></i>Luu</button></div>
    </form>
</div></div></div>

<!-- MODAL SUA -->
<div class="modal fade" id="editModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Chinh sua mon hoc</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST"><input type="hidden" name="action" value="edit"><input type="hidden" name="id" id="eId">
    <div class="modal-body"><div class="row g-3">
        <div class="col-12"><label class="form-label">Nganh</label><select name="major_id" id="eMajor" class="form-select"><?php foreach($majorsArr as $m): ?><option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['major_name']); ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label">Ma mon</label><input type="text" name="subject_code" id="eCode" class="form-control"></div>
        <div class="col-md-8"><label class="form-label">Ten mon</label><input type="text" name="subject_name" id="eName" class="form-control"></div>
        <div class="col-md-2"><label class="form-label">Tin chi</label><input type="number" name="credits" id="eCredits" class="form-control" min="1" max="10"></div>
        <div class="col-md-2"><label class="form-label">Tiet LT</label><input type="number" name="theory_periods" id="eLt" class="form-control" min="0"></div>
        <div class="col-md-2"><label class="form-label">Tiet TH</label><input type="number" name="practice_periods" id="eTh" class="form-control" min="0"></div>
        <div class="col-md-3"><label class="form-label">Loai mon</label><select name="subject_type_new" id="eType" class="form-select"><option value="required">Bat buoc</option><option value="elective">Tu chon</option><option value="general">Dai cuong</option></select></div>
        <div class="col-md-3"><label class="form-label">Hoc ky</label><select name="semester_order" id="eSem" class="form-select"><?php for($i=1;$i<=11;$i++): ?><option value="<?php echo $i; ?>">Hoc ky <?php echo $i; ?></option><?php endfor; ?></select></div>
        <div class="col-12"><label class="form-label">Mo ta</label><textarea name="description" id="eDesc" class="form-control" rows="2"></textarea></div>
    </div></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Huy</button><button type="submit" class="btn btn-gold"><i class="bi bi-save me-1"></i>Cap nhat</button></div>
    </form>
</div></div></div>

<!-- MODAL IMPORT EXCEL -->
<div class="modal fade" id="importModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header bg-success text-white"><h5 class="modal-title"><i class="bi bi-file-earmark-excel-fill me-2"></i>Import tu file Excel/CSV</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="alert alert-info">
            <strong>Dinh dang file:</strong> Excel (.xlsx) hoac CSV<br>
            <strong>Cac cot theo thu tu:</strong><br>
            <code>subject_code | subject_name | credits | subject_type | semester_order | theory_periods | practice_periods | description</code><br>
            <small class="text-muted">subject_type: required / elective / general (hoac: Bat buoc / Tu chon / Dai cuong)</small>
        </div>
        <div class="mb-3">
            <a href="download_template.php" class="btn btn-outline-success btn-sm">
                <i class="bi bi-download me-1"></i>Tai file mau CSV
            </a>
        </div>
        <div id="importResult" style="display:none"></div>
        <form id="importForm" enctype="multipart/form-data">
            <input type="hidden" name="major_id" value="<?php echo $filter_major; ?>">
            <div class="mb-3">
                <label class="form-label fw-bold">Chon file Excel/CSV <span class="text-danger">*</span></label>
                <input type="file" name="excel_file" id="excelFile" class="form-control" accept=".csv,.xlsx,.xls" required>
                <div class="form-text">Ho tro: .xlsx, .csv | Toi da 5MB</div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">Che do import</label>
                <div class="d-flex gap-3">
                    <div class="form-check"><input class="form-check-input" type="radio" name="mode" value="append" id="modeAppend" checked><label class="form-check-label" for="modeAppend">Them vao (giu mon cu)</label></div>
                    <div class="form-check"><input class="form-check-input" type="radio" name="mode" value="replace" id="modeReplace"><label class="form-check-label" for="modeReplace">Thay the (xoa mon cu truoc)</label></div>
                </div>
            </div>
        </form>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Huy</button>
        <button type="button" class="btn btn-success" id="btnImport">
            <i class="bi bi-upload me-1"></i>Import
        </button>
    </div>
</div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/university/assets/js/main.js"></script>
<script>
document.getElementById('editModal').addEventListener('show.bs.modal', function(e) {
    const b = e.relatedTarget;
    document.getElementById('eId').value      = b.dataset.id;
    document.getElementById('eMajor').value   = b.dataset.major;
    document.getElementById('eCode').value    = b.dataset.code;
    document.getElementById('eName').value    = b.dataset.name;
    document.getElementById('eCredits').value = b.dataset.credits;
    document.getElementById('eType').value    = b.dataset.type;
    document.getElementById('eSem').value     = b.dataset.sem;
    document.getElementById('eLt').value      = b.dataset.lt;
    document.getElementById('eTh').value      = b.dataset.th;
    document.getElementById('eDesc').value    = b.dataset.desc;
});

document.getElementById('btnImport').addEventListener('click', function() {
    const form = document.getElementById('importForm');
    const fileInput = document.getElementById('excelFile');
    if (!fileInput.files.length) { alert('Vui long chon file!'); return; }
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Dang xu ly...';
    const fd = new FormData(form);
    fetch('import_curriculum.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            const res = document.getElementById('importResult');
            res.style.display = 'block';
            res.className = 'alert ' + (data.success ? 'alert-success' : 'alert-danger');
            res.innerHTML = '<i class="bi bi-' + (data.success ? 'check-circle-fill' : 'exclamation-circle-fill') + ' me-2"></i>' + data.message;
            if (data.success) {
                setTimeout(() => { window.location.href = '?major_id=<?php echo $filter_major; ?>'; }, 1500);
            }
        })
        .catch(() => {
            document.getElementById('importResult').style.display = 'block';
            document.getElementById('importResult').className = 'alert alert-danger';
            document.getElementById('importResult').textContent = 'Loi ket noi. Vui long thu lai.';
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-upload me-1"></i>Import';
        });
});
</script>
</body></html>
