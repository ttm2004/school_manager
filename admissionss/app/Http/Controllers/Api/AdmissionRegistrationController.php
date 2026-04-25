<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Exception;


class AdmissionRegistrationController extends Controller
{
    //
    public function store(Request $request)
    {
        /*
         * Validate input
         */
        $validator = Validator::make($request->all(), [
            'fullname' => ['required', 'string', 'max:255'],
            'birthday' => ['required', 'date'],
            'phone' => [
                'required',
                'regex:/^(0)[0-9]{9}$/',
                Rule::unique('registrations', 'phone'),
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('registrations', 'email'),
            ],
            'identification' => [
                'required',
                'regex:/^[0-9]{12}$/',
                Rule::unique('registrations', 'identification'),
            ],
            'address' => ['required', 'string'],
            'graduation_year' => ['required', 'integer'],
            'school' => ['required', 'string', 'max:255'],
            'major' => ['required'],
            'method' => ['required', 'string', 'max:50'],
            'combination' => ['nullable'],
            'gender' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string'],
            'newsletter' => ['nullable'],
            'scores' => ['nullable', 'array'],

            // Nếu form có upload file
            'transcript' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'direct_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ], [
            'fullname.required' => 'Vui lòng nhập họ và tên',
            'birthday.required' => 'Vui lòng chọn ngày sinh',
            'phone.required' => 'Vui lòng nhập số điện thoại',
            'phone.regex' => 'Số điện thoại không hợp lệ',
            'phone.unique' => 'Số điện thoại đã được đăng ký',
            'email.required' => 'Vui lòng nhập email',
            'email.email' => 'Email không hợp lệ',
            'email.unique' => 'Email đã được đăng ký',
            'identification.required' => 'Vui lòng nhập CCCD',
            'identification.regex' => 'CCCD không hợp lệ, phải gồm 12 số',
            'identification.unique' => 'CCCD đã được đăng ký',
            'address.required' => 'Vui lòng nhập địa chỉ',
            'graduation_year.required' => 'Vui lòng chọn năm tốt nghiệp',
            'school.required' => 'Vui lòng nhập trường THPT',
            'major.required' => 'Vui lòng chọn ngành đăng ký',
            'method.required' => 'Vui lòng chọn phương thức xét tuyển',
            'transcript.mimes' => 'File học bạ chỉ chấp nhận PDF, JPG, JPEG, PNG',
            'direct_file.mimes' => 'File minh chứng chỉ chấp nhận PDF, JPG, JPEG, PNG',
            'transcript.max' => 'File học bạ không được vượt quá 5MB',
            'direct_file.max' => 'File minh chứng không được vượt quá 5MB',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            /*
             * Upload file nếu có
             */
            $transcriptFile = null;
            $directFile = null;

            if ($request->hasFile('transcript')) {
                $transcriptFile = $this->uploadFile($request->file('transcript'), 'registrations');
            }

            if ($request->hasFile('direct_file')) {
                $directFile = $this->uploadFile($request->file('direct_file'), 'registrations');
            }

            /*
             * Insert registrations
             */
            $registrationId = DB::table('registrations')->insertGetId([
                'fullname' => trim($request->input('fullname')),
                'birthday' => $request->input('birthday'),
                'gender' => $request->input('gender'),
                'identification' => trim($request->input('identification')),
                'phone' => trim($request->input('phone')),
                'email' => trim($request->input('email')),
                'address' => trim($request->input('address')),
                'graduation_year' => (int) $request->input('graduation_year'),
                'school' => trim($request->input('school')),
                'major' => $request->input('major'),
                'method' => $request->input('method'),
                'combination_id' => $request->input('combination'),
                'province_id' => $request->input('province'),
                'district_id' => $request->input('district'),
                'file_path' => $transcriptFile ?? $directFile,
                // 'notes' => $request->input('notes'),
                'status' => 'pending',
                'created_at' => now(),
            ]);

            /*
             * Insert điểm xét tuyển
             */
            if ($request->has('scores') && is_array($request->scores)) {
                $scores = $request->scores;
                $totalScore = $this->calculateTotalScore($request->input('method'), $scores);

                DB::table('diemtuyensinh')->insert([
                    'registration_id' => $registrationId,
                    'method' => $request->input('method'),
                    'score_data' => json_encode($scores, JSON_UNESCAPED_UNICODE),
                    'total_score' => $totalScore,
                    'created_at' => now(),
                ]);
            }

            /*
             * Newsletter
             */
            if ($request->has('newsletter')) {
                $exists = DB::table('newsletter')
                    ->where('email', trim($request->email))
                    ->exists();

                if (!$exists) {
                    DB::table('newsletter')->insert([
                        'email' => trim($request->email),
                        'subscribed_at' => now(),
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Đăng ký thành công! Mã hồ sơ: ' . str_pad($registrationId, 8, '0', STR_PAD_LEFT),
                'data' => [
                    'id' => $registrationId,
                    'code' => str_pad($registrationId, 8, '0', STR_PAD_LEFT),
                ],
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Lỗi hệ thống: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function calculateTotalScore(string $method, array $scores): float
    {
        switch ($method) {
            case 'thpt':
                return (float) ($scores['math'] ?? 0)
                    + (float) ($scores['physic'] ?? 0)
                    + (float) ($scores['chemistry'] ?? 0);

            case 'hocba':
                $sum = 0;
                $count = 0;

                foreach ($scores as $value) {
                    if (is_numeric($value)) {
                        $sum += (float) $value;
                        $count++;
                    }
                }

                return $count > 0 ? round($sum / $count, 2) : 0;

            case 'dgnl':
                return (float) ($scores['dgnl'] ?? 0);

            default:
                return 0;
        }
    }

    private function uploadFile($file, string $folder): string
    {
        $filename = time() . '_' . uniqid() . '_' . $file->getClientOriginalName();

        $file->move(public_path('uploads/' . $folder), $filename);

        return 'uploads/' . $folder . '/' . $filename;
    }
}
