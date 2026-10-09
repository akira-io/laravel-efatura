<?php

declare(strict_types=1);

use Akira\Efatura\Actions\BuildEventIdAction;
use Akira\Efatura\Actions\ParseEventIdAction;
use Akira\Efatura\Data\EventIdData;
use Akira\Efatura\Enums\Environment;
use Akira\Efatura\Tests\Support\IdentifierFixtures as I;
use Carbon\CarbonImmutable;

it('builds the identifier of the official event example', function (): void {
    $data = EventIdData::from(['repositoryCode' => 1, 'issueDateTime' => '2021-08-05T18:10:11', 'taxId' => '123456789']);

    expect(resolve(BuildEventIdAction::class)->handle($data))->toBe(I::OFFICIAL_EVENT_ID);
});

it('reads every component of the official event example', function (): void {
    $data = resolve(ParseEventIdAction::class)->handle(I::OFFICIAL_EVENT_ID);

    expect($data->repository)->toBe(Environment::Production)
        ->and($data->issueDateTime->format('Y-m-d H:i:s'))->toBe('2021-08-05 18:10:11')
        ->and($data->taxId)->toBe('123456789');
});

it('builds an identifier the parser reads back for every repository', function (Environment $repository): void {
    $data = EventIdData::from(['repositoryCode' => $repository->value, 'issueDateTime' => '2099-12-31T23:59:59', 'taxId' => '999999999']);

    $eventId = resolve(BuildEventIdAction::class)->handle($data);

    expect($eventId)->toBe('CV' . $repository->value . '991231235959999999999')
        ->and(resolve(ParseEventIdAction::class)->handle($eventId)->toPayload())->toBe($data->toPayload());
})->with(fn (): array => Environment::cases());

it('writes the issue instant in Cabo Verde time', function (): void {
    $data = EventIdData::from(['repositoryCode' => 3, 'issueDateTime' => CarbonImmutable::parse('2026-10-03T00:30:00Z'), 'taxId' => '123456789']);

    expect(resolve(BuildEventIdAction::class)->handle($data))->toBe('CV3261002233000123456789');
});

it('validates an identifier built with new before composing it', function (): void {
    $data = new EventIdData(Environment::Test, CarbonImmutable::parse('2026-10-02 12:00:00', 'Atlantic/Cape_Verde'), '012345678');

    expect(fn (): string => resolve(BuildEventIdAction::class)->handle($data))->toFailValidationOn('taxId', 'The tax id field format is invalid.');
});

it('rejects event identifier components outside their official bounds', function (array $payload, string $field, string $message): void {
    expect(fn (): EventIdData => EventIdData::from([...['repositoryCode' => 1, 'issueDateTime' => '2026-10-02T12:00:00', 'taxId' => '123456789'], ...$payload]))
        ->toFailValidationOn($field, $message);
})->with([
    'tax id starting with zero' => [['taxId' => '012345678'], 'taxId', 'The tax id field format is invalid.'],
    'tax id with ten digits'    => [['taxId' => '1234567890'], 'taxId', 'The tax id field format is invalid.'],
    'unknown repository'        => [['repositoryCode' => 4], 'repositoryCode', 'The selected repository code is invalid.'],
    'before the fiscal epoch'   => [['issueDateTime' => '2020-12-31T23:59:59'], 'issueDateTime', 'The issue date time must use a valid fiscal date or time.'],
    'after the two digit year'  => [['issueDateTime' => '2100-01-01T00:00:00'], 'issueDateTime', 'The issue date time field must be a date before 2100-01-01.'],
]);

it('rejects identifiers that are not official event identifiers', function (string $eventId): void {
    expect(fn (): EventIdData => resolve(ParseEventIdAction::class)->handle($eventId))
        ->toFailValidationOn('eventId', 'The eventId must be an official event identifier.');
})->with([
    'hour twenty four'          => ['CV1210805241011123456789'],
    'minute sixty'              => ['CV1210805186011123456789'],
    'second sixty'              => ['CV1210805181060123456789'],
    'month zero'                => ['CV1210005181011123456789'],
    'thirtieth of February'     => ['CV1210230181011123456789'],
    'tax id starting with zero' => ['CV1210805181011023456789'],
    'twenty three characters'   => ['CV121080518101112345678'],
    'twenty five characters'    => ['CV12108051810111234567890'],
    'letter in the time'        => ['CV12108051810A1123456789'],
    'before the fiscal epoch'   => ['CV1201231235959123456789'],
    'repository zero'           => ['CV0210805181011123456789'],
    'repository four'           => ['CV4210805181011123456789'],
]);

it('reports a failure at the field the caller names', function (): void {
    expect(fn (): EventIdData => resolve(ParseEventIdAction::class)->handle('bad', 'id'))
        ->toFailValidationOn('id', 'The id must be an official event identifier.');
});
