<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Models\Customer;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Validator;
use League\Csv\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

class ImportCustomers extends Page
{
    protected static string $resource = CustomerResource::class;

    protected string $view = 'filament.pages.import-customers';

    public ?array $data = [];

    public array $fileHeaders = [];

    public array $fileRows = [];

    public bool $fileParsed = false;

    public int $totalRows = 0;

    public ?string $fileName = null;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadSample')
                ->label('Download Sample')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function () {
                    return response()->download(storage_path('app/customer-import-example.csv'), 'customer-import-example.csv');
                }),
        ];
    }

    public function mount(): void
    {
        abort_unless(in_array(auth()->user()->role, ['admin', 'manager', 'lead', 'rep']), 403);

        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->columns(1)
            ->components([
                FileUpload::make('file')
                    ->label('Upload Spreadsheet')
                    ->helperText('Supports CSV (.csv) and Excel (.xlsx, .xls) files. First row must contain column headers.')
                    ->acceptedFileTypes([
                        'text/csv',
                        'text/x-csv',
                        'application/csv',
                        'application/x-csv',
                        'text/comma-separated-values',
                        'text/x-comma-separated-values',
                        'text/plain',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ])
                    ->maxSize(20480)
                    ->live()
                    ->afterStateUpdated(function ($state): void {
                        if ($state) {
                            $this->parseFile($state);
                        }
                    }),
                Section::make('Column Mapping')
                    ->description('Map your spreadsheet columns to customer fields.')
                    ->visible(fn (): bool => $this->fileParsed)
                    ->columns(2)
                    ->schema([
                        Select::make('mapping.customer_name')
                            ->label('Customer Name *')
                            ->options(fn (): array => array_combine($this->fileHeaders, $this->fileHeaders))
                            ->placeholder('-- Select column --')
                            ->required(),
                        Select::make('mapping.phone_number')
                            ->label('Phone Number *')
                            ->options(fn (): array => array_combine($this->fileHeaders, $this->fileHeaders))
                            ->placeholder('-- Select column --')
                            ->required(),
                        Select::make('mapping.address')
                            ->label('Address')
                            ->options(fn (): array => array_combine($this->fileHeaders, $this->fileHeaders))
                            ->placeholder('-- Select column --'),
                        Select::make('mapping.city')
                            ->label('City')
                            ->options(fn (): array => array_combine($this->fileHeaders, $this->fileHeaders))
                            ->placeholder('-- Select column --'),
                        Select::make('mapping.state')
                            ->label('State')
                            ->options(fn (): array => array_combine($this->fileHeaders, $this->fileHeaders))
                            ->placeholder('-- Select column --'),
                        Select::make('mapping.age')
                            ->label('Age')
                            ->options(fn (): array => array_combine($this->fileHeaders, $this->fileHeaders))
                            ->placeholder('-- Select column --'),
                        Select::make('mapping.gender')
                            ->label('Gender')
                            ->options(fn (): array => array_combine($this->fileHeaders, $this->fileHeaders))
                            ->placeholder('-- Select column --'),
                        Select::make('mapping.priority')
                            ->label('Priority')
                            ->options(fn (): array => array_combine($this->fileHeaders, $this->fileHeaders))
                            ->placeholder('-- Select column --'),
                        Select::make('mapping.customer_status')
                            ->label('Customer Status')
                            ->options(fn (): array => array_combine($this->fileHeaders, $this->fileHeaders))
                            ->placeholder('-- Select column --'),
                    ])
                    ->footerActions([
                        Action::make('doImport')
                            ->label(fn (): string => 'Import '.$this->totalRows.' Customers')
                            ->icon('heroicon-o-arrow-up-tray')
                            ->color('primary')
                            ->action('import'),
                        Action::make('doReset')
                            ->label('Cancel')
                            ->icon('heroicon-o-x-mark')
                            ->color('gray')
                            ->action('resetImport'),
                    ]),
            ]);
    }

    public function parseFile($file): void
    {
        if (! $file) {
            return;
        }

        $path = $file->getRealPath();
        $extension = strtolower($file->getClientOriginalExtension());

        $this->fileName = $file->getClientOriginalName();

        try {
            if (in_array($extension, ['xlsx', 'xls'])) {
                $this->parseXlsx($path);
            } else {
                $this->parseCsv($path);
            }

            if (empty($this->fileHeaders)) {
                Notification::make()
                    ->title('No data found')
                    ->body('The file appears to be empty or has no readable headers.')
                    ->danger()
                    ->send();

                return;
            }

            $this->fileParsed = true;
            $this->totalRows = count($this->fileRows);
            $this->data['mapping'] = [];

            Notification::make()
                ->title('File parsed successfully')
                ->body('Found '.count($this->fileHeaders).' columns and '.$this->totalRows.' rows.')
                ->success()
                ->send();
        } catch (\Exception $e) {
            $this->fileParsed = false;
            $this->fileHeaders = [];
            $this->fileRows = [];

            Notification::make()
                ->title('Failed to parse file')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function parseCsv(string $path): void
    {
        $csvReader = CsvReader::createFromPath($path, 'r');
        $csvReader->setHeaderOffset(0);

        $headers = $csvReader->getHeader();
        $this->fileHeaders = array_values(array_map('trim', $headers));

        $records = iterator_to_array($csvReader->getRecords());
        $this->fileRows = array_values($records);
    }

    protected function parseXlsx(string $path): void
    {
        $reader = new XlsxReader;
        $reader->open($path);

        foreach ($reader->getSheetIterator() as $sheet) {
            $rowIterator = $sheet->getRowIterator();
            $rowIterator->rewind();

            if (! $rowIterator->valid()) {
                continue;
            }

            $headerRow = $rowIterator->current();
            $headerCells = $headerRow->getCells();
            $headers = [];
            foreach ($headerCells as $cell) {
                $headers[] = trim((string) $cell->getValue());
            }
            $this->fileHeaders = $headers;

            $rowIterator->next();

            $rows = [];
            while ($rowIterator->valid()) {
                $row = $rowIterator->current();
                $cells = $row->getCells();
                $rowData = [];
                foreach ($cells as $index => $cell) {
                    $rowData[$headers[$index] ?? 'col_'.$index] = $cell->getValue();
                }
                $rows[] = $rowData;
                $rowIterator->next();
            }

            $this->fileRows = $rows;
            break;
        }

        $reader->close();
    }

    public function import(): void
    {
        $mapping = $this->data['mapping'] ?? [];

        if (empty($mapping['customer_name']) || empty($mapping['phone_number'])) {
            Notification::make()
                ->title('Mapping incomplete')
                ->body('Customer Name and Phone Number mappings are required.')
                ->danger()
                ->send();

            return;
        }

        $user = auth()->user();
        $successful = 0;
        $failed = 0;
        $errors = [];

        foreach ($this->fileRows as $index => $row) {
            $rowNumber = $index + 2;

            $customerData = [];
            foreach ($mapping as $field => $header) {
                if ($header && isset($row[$header]) && $row[$header] !== '' && $row[$header] !== null) {
                    $customerData[$field] = $row[$header];
                }
            }

            if (empty($customerData['customer_name']) || empty($customerData['phone_number'])) {
                $failed++;
                $errors[] = "Row {$rowNumber}: Missing required fields";

                continue;
            }

            $customerData['phone_number'] = preg_replace('/[^0-9]/', '', $customerData['phone_number']);

            $validator = Validator::make($customerData, [
                'customer_name' => ['required', 'string', 'max:255'],
                'phone_number' => ['required', 'string', 'max:11', 'unique:customers,phone_number'],
                'age' => ['nullable', 'integer', 'min:0', 'max:150'],
                'gender' => ['nullable', 'in:male,female'],
                'priority' => ['nullable', 'in:high,medium,low'],
                'customer_status' => ['nullable', 'string', 'max:255'],
            ]);

            if ($validator->fails()) {
                $failed++;
                $errorMsg = collect($validator->errors()->all())->implode('; ');
                $errors[] = "Row {$rowNumber}: {$errorMsg}";

                continue;
            }

            try {
                $customer = Customer::create(collect($customerData)->except(['leads', 'reps'])->toArray());

                if ($user->role === 'lead') {
                    $customer->updateQuietly([
                        'lead_id' => $user->id,
                        'agent_id' => $user->id,
                        'rep_acceptance_status' => 'accepted',
                    ]);
                    $customer->leads()->syncWithoutDetaching([$user->id]);
                } elseif ($user->role === 'rep') {
                    $customer->updateQuietly([
                        'rep_id' => $user->id,
                        'lead_id' => $user->lead_id ?? null,
                        'rep_acceptance_status' => 'accepted',
                    ]);
                    $customer->reps()->syncWithoutDetaching([$user->id]);
                    if ($user->lead_id) {
                        $customer->leads()->syncWithoutDetaching([$user->lead_id]);
                    }
                }

                $successful++;
                $this->dispatch('refresh-dashboard');
            } catch (\Exception $e) {
                $failed++;
                $errors[] = "Row {$rowNumber}: ".$e->getMessage();
            }
        }

        $body = "Successfully imported {$successful} customers.";
        if ($failed > 0) {
            $body .= " {$failed} rows failed.";
        }

        Notification::make()
            ->title('Import completed')
            ->body($body)
            ->success()
            ->send();

        if (! empty($errors)) {
            Notification::make()
                ->title('Import errors')
                ->body(implode("\n", array_slice($errors, 0, 10)))
                ->warning()
                ->persistent()
                ->send();
        }

        $this->fileParsed = false;
        $this->fileHeaders = [];
        $this->fileRows = [];
        $this->totalRows = 0;
        $this->fileName = null;
        $this->data = [];
        $this->form->fill();
    }

    public function resetImport(): void
    {
        $this->fileParsed = false;
        $this->fileHeaders = [];
        $this->fileRows = [];
        $this->totalRows = 0;
        $this->fileName = null;
        $this->data = [];
        $this->form->fill();
    }
}
