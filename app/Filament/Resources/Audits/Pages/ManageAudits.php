<?php

namespace App\Filament\Resources\Audits\Pages;

use App\Filament\Resources\Audits\AuditResource;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use OwenIt\Auditing\Models\Audit;

class ManageAudits extends ManageRecords
{
    protected static string $resource = AuditResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('userActivityReport')
                ->label('User Activity Report')
                ->icon('heroicon-o-document-arrow-down')
                ->color('info')
                ->visible(fn (): bool => auth()->user()->hasAnyRole(['admin', 'manager', 'general_manager']))
                ->modalHeading('User Activity Report')
                ->modalDescription('Select a user and an optional date range to generate a PDF of everything they have done. Leave the dates empty for the user\'s full history.')
                ->form([
                    Select::make('user_id')
                        ->label('User')
                        ->searchable()
                        ->required()
                        ->options(fn (): array => User::query()
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->default(fn (): ?string => $this->getTableFilterState('user_id')['value'] ?? null),
                    DatePicker::make('created_from')
                        ->label('From')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->closeOnDateSelection(),
                    DatePicker::make('created_until')
                        ->label('To')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->closeOnDateSelection(),
                ])
                ->action(function (array $data) {
                    $user = User::findOrFail($data['user_id']);

                    $report = static::buildReportData(
                        $user,
                        $data['created_from'] ?? null,
                        $data['created_until'] ?? null,
                    );

                    if ($report['audits']->isEmpty()) {
                        Notification::make()
                            ->title('No activity found')
                            ->body('There are no audit records for this user in the selected date range.')
                            ->warning()
                            ->send();

                        return;
                    }

                    $pdf = Pdf::loadView('pdf.user-activity-report', $report);

                    $filename = sprintf(
                        'user-activity-report-%s-%s.pdf',
                        Str::slug($user->name) ?: 'user',
                        now()->format('Y_m_d_H_i_s'),
                    );

                    return response()->streamDownload(
                        fn () => print $pdf->output(),
                        $filename,
                        ['Content-Type' => 'application/pdf'],
                    );
                }),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function buildReportData(User $user, ?string $from = null, ?string $until = null): array
    {
        $query = Audit::query()
            ->where('user_type', User::class)
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc');

        if (filled($from)) {
            $query->whereDate('created_at', '>=', $from);
        }

        if (filled($until)) {
            $query->whereDate('created_at', '<=', $until);
        }

        /** @var Collection<int, Audit> $audits */
        $audits = $query->get();

        return [
            'user' => $user,
            'audits' => $audits,
            'from' => $from,
            'until' => $until,
            'total' => $audits->count(),
            'eventCounts' => $audits->countBy('event')->sortKeys(),
            'modelCounts' => $audits
                ->countBy(fn (Audit $audit): string => $audit->auditable_type
                    ? class_basename($audit->auditable_type)
                    : 'N/A')
                ->sortDesc(),
            'firstActivity' => $audits->first()?->created_at,
            'lastActivity' => $audits->last()?->created_at,
            'generatedBy' => auth()->user(),
            'generatedAt' => now(),
        ];
    }
}
