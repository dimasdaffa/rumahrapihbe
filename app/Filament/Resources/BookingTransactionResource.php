<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BookingTransactionResource\Pages;
use App\Filament\Resources\BookingTransactionResource\RelationManagers;
use App\Models\BookingTransaction;
use App\Models\HomeService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BookingTransactionResource extends Resource
{
    protected static ?string $model = BookingTransaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    public static function updateTotals(Get $get, Set $set): void
    {
        $selectedHomeServices = collect($get('transactionDetails'))
            ->filter(fn($item) => !empty($item['home_service_id']));

        $prices = HomeService::find($selectedHomeServices->pluck('home_service_id'))
            ->pluck('price', 'id');

        $subTotal = $selectedHomeServices->reduce(function ($subtotal, $item) use ($prices) {
            return $subtotal + ($prices[$item['home_service_id']] ?? 0);
        }, 0);

        $total_tax_amount = round($subTotal * 0.11); // Assuming 11% tax rate

        $total_amount = round($subTotal + $total_tax_amount);

        $set('total_amount', number_format($total_amount, 0, ',', '.'));
        $set('total_tax_amount', number_format($total_tax_amount, 0, ',', '.'));
        $set('sub_total', number_format($subTotal, 0, ',', '.'));
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Wizard::make([
                    Forms\Components\Wizard\Step::make('Product and Price')
                        ->completedIcon('heroicon-o-hand-thumb-up')
                        ->description('Add your product items')
                        ->schema([
                            Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\Repeater::make('transactionDetails')
                                        ->relationship('transactionDetails')
                                        ->schema([
                                            Forms\Components\Select::make('home_service_id')
                                                ->relationship('homeService', 'name')
                                                ->searchable()
                                                ->preload()
                                                ->required()
                                                ->label('Select Product')
                                                ->live()
                                                ->afterStateUpdated(function ($state, Forms\Set $set) {
                                                    $home_service = HomeService::find($state);
                                                    $set('price', $home_service ? $home_service->price : 0);
                                                }),
                                            Forms\Components\TextInput::make('price')
                                                ->required()
                                                ->numeric()
                                                ->readonly()
                                                ->label('Price')
                                                ->hint('Price will be filled automatically based on product selection'),
                                        ])
                                        ->live() // This Repeater is set to live
                                        ->afterStateUpdated(function (Get $get, Set $set) {
                                            self::updateTotals($get, $set);
                                        })
                                        ->minItems(1)
                                        ->columnSpan('full')
                                        ->label('Choose Products'), // Label for the Repeater itself
                                    Forms\Components\Grid::make(3)
                                        ->schema([
                                            Forms\Components\TextInput::make('sub_total')
                                                ->numeric()
                                                ->readonly()
                                                ->label('Sub Total Amount'),
                                            Forms\Components\TextInput::make('total_amount')
                                                ->numeric()
                                                ->readonly()
                                                ->label('Total Amount'),
                                            Forms\Components\TextInput::make('total_tax_amount')
                                                ->numeric()
                                                ->readonly()
                                                ->label('Total Tax (11%)'),
                                        ]),
                                ]),
                        ]),
                    Forms\Components\Wizard\Step::make('Customer Information')
                        ->completedIcon('heroicon-o-hand-thumb-up')
                        ->description('Good for our marketing')
                        ->schema([
                            Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\TextInput::make('name')
                                        ->required()
                                        ->maxLength(255),
                                    Forms\Components\TextInput::make('phone')
                                        ->required()
                                        ->maxLength(255),
                                    Forms\Components\TextInput::make('email')
                                        ->required()
                                        ->maxLength(255),
                                ]),
                        ]),
                    Forms\Components\Wizard\Step::make('Delivery Information')
                        ->completedIcon('heroicon-o-hand-thumb-up')
                        ->description('Put your correct address')
                        ->schema([
                            Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\TextInput::make('city')
                                        ->required()
                                        ->maxLength(255),
                                    Forms\Components\TextInput::make('post_code')
                                        ->required()
                                        ->maxLength(255),
                                    Forms\Components\DatePicker::make('schedule_at')
                                        ->required(),
                                    Forms\Components\TimePicker::make('started_time')
                                        ->required(),
                                    Forms\Components\Textarea::make('address')
                                        ->required()
                                        ->maxLength(255),
                                ]),
                        ]),
                    Forms\Components\Wizard\Step::make('Payment Information')
                        ->completedIcon('heroicon-o-hand-thumb-up')
                        ->description('Review your payment!')
                        ->schema([
                            Forms\Components\Grid::make(3)
                                ->schema([
                                    Forms\Components\TextInput::make('booking_trx_id')
                                        ->required()
                                        ->maxLength(255),
                                    Forms\Components\ToggleButtons::make('is_paid')
                                        ->label('Apakah sudah membayar?') // Indonesian: "Has it been paid?"
                                        ->boolean()
                                        ->grouped()
                                        ->icons([
                                            true => 'heroicon-o-pencil', // This icon seems incorrect for "paid"
                                            false => 'heroicon-o-clock',
                                        ])
                                        ->required(),
                                    Forms\Components\FileUpload::make('proof')
                                        ->image()
                                        ->required(),
                                ]),
                        ]),
                ])
                    ->columnSpan('full')
                    ->columns(1)
                    ->skippable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('booking_trx_id')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime() // Assuming it's a datetime column
                    ->sortable() // Added based on typical usage
                    ->toggleable(isToggledHiddenByDefault: true), // Added based on typical usage for created_at
                Tables\Columns\IconColumn::make('is_paid')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->label('Terverifikasi'),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\Action::make('approve') // Tombol approve menghilang ketika is_paid is true begitu pun sebaliknya jika is_paid is false aka muncul lagi
                    ->label('Approve')
                    ->action(function (BookingTransaction $record) {
                        $record->is_paid = true;
                        $record->save();

                        Notification::make() // Use the imported Notification facade
                            ->success()
                            ->title('Order Approved')
                            ->body('The order has been successfully approved.')
                            ->send();
                    })
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn(BookingTransaction $record): bool => !$record->is_paid),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBookingTransactions::route('/'),
            'create' => Pages\CreateBookingTransaction::route('/create'),
            'edit' => Pages\EditBookingTransaction::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
