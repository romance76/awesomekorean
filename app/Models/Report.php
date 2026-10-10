<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Report extends Model
{
    protected $fillable = ['reporter_id','reportable_type','reportable_id','reason','content','status','admin_note','handled_at','handled_by'];
    protected $casts = ['handled_at' => 'datetime'];

    public function reporter() { return $this->belongsTo(User::class, 'reporter_id'); }
    public function handler() { return $this->belongsTo(User::class, 'handled_by'); }
    public function reportable() { return $this->morphTo(); }
    public function logs() { return $this->hasMany(ReportLog::class)->orderBy('created_at')->orderBy('id'); }

    /**
     * 처리 이력 한 줄 남기기 — 신고를 접수/해결/기각/재오픈/메모할 때 항상 이걸 쓴다.
     * $actor 가 null 이면(신고자 본인의 접수 등) 이름은 $actorName 으로.
     */
    public function addLog(string $action, ?string $from, ?string $to, ?User $actor = null, ?string $note = null, ?string $actorName = null): ReportLog
    {
        return ReportLog::create([
            'report_id' => $this->id, 'action' => $action, 'from_status' => $from, 'to_status' => $to,
            'admin_id' => $actor?->id, 'actor_name' => $actor ? ($actor->nickname ?: $actor->name) : $actorName,
            'note' => $note !== null && $note !== '' ? mb_substr($note, 0, 1000) : null,
            'estimated' => false, 'created_at' => now(),
        ]);
    }

    /**
     * 상태 변경 + 이력 기록을 한 번에. (해결/기각 → handled_at·handled_by 기록, 다시 대기로 돌리면 비움)
     * 상태가 같고 메모만 달라지면 'note' 이력만 남긴다.
     */
    public function applyUpdate(?string $newStatus, ?string $newNote, ?User $actor): void
    {
        $old = $this->status;
        $oldNote = $this->admin_note;
        $changedStatus = $newStatus !== null && $newStatus !== $old;
        $changedNote = $newNote !== null && $newNote !== (string) $oldNote;

        $data = [];
        if ($newStatus !== null) $data['status'] = $newStatus;
        if ($newNote !== null) $data['admin_note'] = $newNote;
        if ($changedStatus) {
            if ($newStatus === 'pending') { $data['handled_at'] = null; $data['handled_by'] = null; }
            else { $data['handled_at'] = now(); $data['handled_by'] = $actor?->id; }
        }
        if ($data) $this->update($data);

        if ($changedStatus) {
            $action = $newStatus === 'pending' ? 'reopened' : ($newStatus === 'resolved' ? 'resolved' : ($newStatus === 'dismissed' ? 'dismissed' : 'status'));
            $this->addLog($action, $old, $newStatus, $actor, $newNote !== null ? $newNote : $this->admin_note);
        } elseif ($changedNote) {
            $this->addLog('note', $old, $old, $actor, $newNote);
        }
    }
}