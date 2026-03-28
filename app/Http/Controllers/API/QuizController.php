<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PointLog;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QuizController extends Controller
{
    public function today()
    {
        $today    = now()->toDateString();
        $question = QuizQuestion::where('quiz_date', $today)
            ->where('is_active', true)
            ->first();

        if (!$question) {
            $question = QuizQuestion::where('is_active', true)
                ->whereNull('quiz_date')
                ->inRandomOrder()
                ->first();
        }

        if (!$question) {
            return response()->json(['message' => '오늘의 퀴즈가 없습니다.'], 404);
        }

        $attempted = QuizAttempt::where('user_id', Auth::id())
            ->where('quiz_question_id', $question->id)
            ->first();

        $data = [
            'id'         => $question->id,
            'category'   => $question->category,
            'question'   => $question->question,
            'option_a'   => $question->option_a,
            'option_b'   => $question->option_b,
            'option_c'   => $question->option_c,
            'option_d'   => $question->option_d,
            'points'     => $question->points,
            'attempted'  => !!$attempted,
        ];

        if ($attempted) {
            $data['your_answer']     = $attempted->answer;
            $data['correct_answer']  = $question->correct_answer;
            $data['is_correct']      = $attempted->is_correct;
            $data['explanation']     = $question->explanation;
            $data['points_earned']   = $attempted->points_earned;
        }

        return response()->json($data);
    }

    public function answer(Request $request)
    {
        $request->validate([
            'question_id' => 'required|exists:quiz_questions,id',
            'answer'      => 'required|in:A,B,C,D',
        ]);

        $question = QuizQuestion::findOrFail($request->question_id);

        $existing = QuizAttempt::where('user_id', Auth::id())
            ->where('quiz_question_id', $question->id)
            ->first();

        if ($existing) {
            return response()->json(['message' => '이미 답변하셨습니다.', 'attempt' => $existing], 409);
        }

        $isCorrect    = strtoupper($request->answer) === strtoupper($question->correct_answer);
        $pointsEarned = $isCorrect ? $question->points : 0;

        $attempt = QuizAttempt::create([
            'user_id'          => Auth::id(),
            'quiz_question_id' => $question->id,
            'answer'           => strtoupper($request->answer),
            'is_correct'       => $isCorrect,
            'points_earned'    => $pointsEarned,
        ]);

        if ($isCorrect && $pointsEarned > 0) {
            PointLog::create([
                'user_id'     => Auth::id(),
                'type'        => 'earn',
                'amount'      => $pointsEarned,
                'description' => '일일 퀴즈 정답',
            ]);
            User::where('id', Auth::id())->increment('points', $pointsEarned);
        }

        return response()->json([
            'is_correct'    => $isCorrect,
            'correct_answer'=> $question->correct_answer,
            'explanation'   => $question->explanation,
            'points_earned' => $pointsEarned,
        ]);
    }

    public function leaderboard()
    {
        $leaders = QuizAttempt::where('is_correct', true)
            ->selectRaw('user_id, SUM(points_earned) as total_points, COUNT(*) as correct_count')
            ->groupBy('user_id')
            ->orderByDesc('total_points')
            ->limit(10)
            ->with('user:id,name,nickname,profile_photo')
            ->get();

        return response()->json($leaders);
    }
}
