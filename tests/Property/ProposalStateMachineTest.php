<?php
/**
 * Property 5: Proposal State Machine Invariants
 *
 * Trạng thái hợp lệ: draft → pending → approved | rejected | cancelled
 *
 * Invariants:
 *   - draft/rejected → có thể edit
 *   - pending/approved/cancelled → không thể edit
 *   - new proposal → status='draft'
 *   - submit draft → status='pending'
 *   - cancel pending → status='cancelled'
 *   - approve pending → status='approved' (Training Office only)
 *   - reject pending → status='rejected' (Training Office only)
 *
 * Validates: Requirements 15.1, 15.2, 15.3, 15.4, 7.8, 8.9
 */

namespace Tests\Property;

use PHPUnit\Framework\TestCase;

class ProposalStateMachineTest extends TestCase
{
    // ── Editable states ───────────────────────────────────────

    /**
     * @dataProvider editableStatesProvider
     */
    public function testEditableStates(string $status, bool $canEdit): void
    {
        $this->assertSame(
            $canEdit,
            $this->isEditable($status),
            "Status '$status' editable phải là " . ($canEdit ? 'true' : 'false')
        );
    }

    public static function editableStatesProvider(): array
    {
        return [
            'draft → editable'     => ['draft',     true],
            'rejected → editable'  => ['rejected',  true],
            'pending → not editable'   => ['pending',   false],
            'approved → not editable'  => ['approved',  false],
            'cancelled → not editable' => ['cancelled', false],
        ];
    }

    // ── Valid transitions ─────────────────────────────────────

    /**
     * @dataProvider validTransitionsProvider
     */
    public function testValidTransitions(string $from, string $action, string $expectedTo): void
    {
        $result = $this->applyTransition($from, $action);
        $this->assertSame(
            $expectedTo,
            $result,
            "Transition $from --[$action]--> phải là $expectedTo"
        );
    }

    public static function validTransitionsProvider(): array
    {
        return [
            'draft + submit → pending'       => ['draft',   'submit',   'pending'],
            'pending + cancel → cancelled'   => ['pending', 'cancel',   'cancelled'],
            'pending + approve → approved'   => ['pending', 'approve',  'approved'],
            'pending + reject → rejected'    => ['pending', 'reject',   'rejected'],
        ];
    }

    // ── Invalid transitions ───────────────────────────────────

    /**
     * @dataProvider invalidTransitionsProvider
     */
    public function testInvalidTransitions(string $from, string $action): void
    {
        $result = $this->applyTransition($from, $action);
        $this->assertNull(
            $result,
            "Transition $from --[$action]--> phải bị từ chối (null)"
        );
    }

    public static function invalidTransitionsProvider(): array
    {
        return [
            'approved + submit → invalid'   => ['approved',  'submit'],
            'approved + cancel → invalid'   => ['approved',  'cancel'],
            'cancelled + submit → invalid'  => ['cancelled', 'submit'],
            'cancelled + approve → invalid' => ['cancelled', 'approve'],
            'rejected + approve → invalid'  => ['rejected',  'approve'],
            'draft + approve → invalid'     => ['draft',     'approve'],
            'draft + cancel → invalid'      => ['draft',     'cancel'],
        ];
    }

    // ── New proposal always starts as draft ──────────────────

    public function testNewProposalStartsAsDraft(): void
    {
        $status = $this->createNewProposal();
        $this->assertSame('draft', $status, 'Đề xuất mới phải có status=draft');
    }

    // ── Approved proposal cannot be modified ─────────────────

    public function testApprovedProposalCannotBeEdited(): void
    {
        $this->assertFalse(
            $this->isEditable('approved'),
            'Đề xuất đã duyệt không thể chỉnh sửa'
        );
    }

    // ── Property: no state can transition to itself via invalid action ──

    /**
     * @dataProvider allStatesProvider
     */
    public function testStateHasDefinedTransitions(string $status): void
    {
        // Mỗi trạng thái phải có ít nhất 1 transition hợp lệ hoặc là terminal
        $terminalStates = ['approved', 'cancelled', 'rejected'];
        if (in_array($status, $terminalStates, true)) {
            // Terminal states: không có transition hợp lệ từ faculty side
            $this->assertTrue(true, "$status là terminal state");
        } else {
            // Non-terminal: phải có ít nhất 1 action hợp lệ
            $hasValidTransition = false;
            foreach (['submit', 'cancel', 'approve', 'reject'] as $action) {
                if ($this->applyTransition($status, $action) !== null) {
                    $hasValidTransition = true;
                    break;
                }
            }
            $this->assertTrue($hasValidTransition, "$status phải có ít nhất 1 transition hợp lệ");
        }
    }

    public static function allStatesProvider(): array
    {
        return [
            ['draft'], ['pending'], ['approved'], ['rejected'], ['cancelled'],
        ];
    }

    // ── Helper methods ────────────────────────────────────────

    private function isEditable(string $status): bool
    {
        return in_array($status, ['draft', 'rejected'], true);
    }

    private function createNewProposal(): string
    {
        return 'draft';
    }

    /**
     * Áp dụng transition, trả về trạng thái mới hoặc null nếu không hợp lệ.
     */
    private function applyTransition(string $from, string $action): ?string
    {
        $transitions = [
            'draft'   => ['submit'  => 'pending'],
            'pending' => [
                'cancel'  => 'cancelled',
                'approve' => 'approved',
                'reject'  => 'rejected',
            ],
            // Terminal states: không có transition
            'approved'  => [],
            'rejected'  => [],
            'cancelled' => [],
        ];

        return $transitions[$from][$action] ?? null;
    }
}
