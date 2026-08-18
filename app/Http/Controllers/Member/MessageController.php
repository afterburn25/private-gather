<?php
namespace App\Http\Controllers\Member;

use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MessageController
{
    public function index(Request $request)
    {
        $items = Conversation::whereHas('participants', fn ($query) => $query->where('users.id', $request->user()->id))
            ->with(['messages' => fn ($query) => $query->latest()->limit(1)])
            ->latest('updated_at')
            ->get();

        return view('member.messages.index', ['conversations' => $items]);
    }

    public function start(Request $request)
    {
        $data = $request->validate([
            'recipient_id' => 'required|integer|exists:users,id',
            'body' => 'required|string|max:10000',
        ]);

        abort_if((int) $data['recipient_id'] === (int) $request->user()->id, 422);
        $this->assertMessagingAllowed((int) $request->user()->id, (int) $data['recipient_id']);

        $conversation = DB::transaction(function () use ($request, $data) {
            $conversation = Conversation::create(['type' => 'direct']);
            $conversation->participants()->attach(
                [$request->user()->id, $data['recipient_id']],
                ['last_read_at' => now()]
            );
            $conversation->messages()->create([
                'user_id' => $request->user()->id,
                'body' => $data['body'],
                'status' => 'sent',
            ]);

            return $conversation;
        });

        return redirect()->route('messages.show', $conversation);
    }

    public function show(Request $request, Conversation $conversation)
    {
        abort_unless($conversation->participants()->where('users.id', $request->user()->id)->exists(), 404);
        $conversation->participants()->updateExistingPivot($request->user()->id, ['last_read_at' => now()]);

        return view('member.messages.show', [
            'conversation' => $conversation,
            'messages' => $conversation->messages()->with('user')->latest()->paginate(50),
        ]);
    }

    public function store(Request $request, Conversation $conversation)
    {
        abort_unless($conversation->participants()->where('users.id', $request->user()->id)->exists(), 404);
        $data = $request->validate(['body' => 'required|string|max:10000']);

        if ($conversation->type === 'direct') {
            $otherId = (int) $conversation->participants()
                ->where('users.id', '!=', $request->user()->id)
                ->value('users.id');

            abort_unless($otherId > 0, 422, 'This conversation no longer has a recipient.');
            $this->assertMessagingAllowed((int) $request->user()->id, $otherId);
        }

        $conversation->messages()->create([
            'user_id' => $request->user()->id,
            'body' => $data['body'],
            'status' => 'sent',
        ]);
        $conversation->touch();

        return back();
    }

    private function assertMessagingAllowed(int $senderId, int $recipientId): void
    {
        $blocked = DB::table('user_blocks')
            ->where(function ($query) use ($senderId, $recipientId): void {
                $query->where([
                    'user_id' => $senderId,
                    'blocked_user_id' => $recipientId,
                ])->orWhere(function ($reverse) use ($senderId, $recipientId): void {
                    $reverse->where([
                        'user_id' => $recipientId,
                        'blocked_user_id' => $senderId,
                    ]);
                });
            })
            ->exists();

        abort_if($blocked, 403, 'Messaging unavailable for this member.');
    }
}
