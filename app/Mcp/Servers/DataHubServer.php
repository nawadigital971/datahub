<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\DeleteReferenceDataTool;
use App\Mcp\Tools\FindContactsTool;
use App\Mcp\Tools\GetBatchMappingTool;
use App\Mcp\Tools\GetBatchSummaryTool;
use App\Mcp\Tools\GetReferenceDataTool;
use App\Mcp\Tools\ListBatchIssuesTool;
use App\Mcp\Tools\ListReferenceDataTool;
use App\Mcp\Tools\QueryAudienceCountsTool;
use App\Mcp\Tools\RecheckBatchTool;
use App\Mcp\Tools\SaveReferenceDataTool;
use App\Mcp\Tools\SetBatchMappingTool;
use App\Mcp\Tools\StageContactsTool;
use App\Mcp\Tools\UpdateContactsTool;
use App\Mcp\Tools\UpdateStagedRowsTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

/**
 * NAWAA Data Hub over MCP: Claude can stage contacts, map files, check and fix staged rows, keep the reference
 * lists and cleaning rules up to date, correct stored contacts and count audiences.
 * There is deliberately no commit tool — committing an import is human-only, in the dashboard.
 */
#[Name('NAWAA Data Hub')]
#[Version('1.0.0')]
#[Instructions(<<<'TEXT'
    NAWAA Data Hub stores marketing contacts (names, mobiles, landlines, emails) collected from many lists.
    Nothing about cleaning is hard-coded: emirates, areas, countries, sectors, cleaning rules (call codes,
    email typo fixes, junk names, first names, gender words, business words, notes labels, words between
    phone numbers, landline prefixes, column header hints, blocked file names),
    mapping templates and the do-not-contact list are all stored, and you can read and improve them.

    New data from the user:
    1. get_reference_data (or list_reference_data) for sources and valid emirates, areas and sectors.
    2. stage_contacts with the rows (any phone/email format; Arabic and English are fine).
    3. get_batch_summary, then list_batch_issues to see problems and suggestions.
    4. If a problem repeats (an unknown area spelling, a new email typo, a call code, a header word),
       fix it at the source with save_reference_data (e.g. add the spelling as an area alias), then
       recheck_batch so every row is analysed again.
    5. update_staged_rows for one-off fixes (phones, emails, areas, actions). Re-checking replaces these,
       so re-check first.
    6. Tell the user the batch is ready and give them the review_url. A person must review and commit it;
       you cannot commit, and nothing reaches the contacts until they do.

    Uploaded files: get_batch_mapping shows the columns with samples and the current mapping;
    set_batch_mapping changes it (optionally saving a template for files with the same headers).

    Stored contacts: find_contacts, then update_contacts to correct names, gender, area, sector,
    junk or do-not-contact. Every change is logged with the user's name.

    query_audience_counts answers "how many people could we reach with…" questions. It returns counts only.
    Never stage employee or staff data (for example medical insurance lists).
    Only delete reference items the user asked you to delete.
    TEXT)]
class DataHubServer extends Server
{
    protected array $tools = [
        GetReferenceDataTool::class,
        ListReferenceDataTool::class,
        SaveReferenceDataTool::class,
        DeleteReferenceDataTool::class,
        StageContactsTool::class,
        GetBatchMappingTool::class,
        SetBatchMappingTool::class,
        GetBatchSummaryTool::class,
        ListBatchIssuesTool::class,
        UpdateStagedRowsTool::class,
        RecheckBatchTool::class,
        FindContactsTool::class,
        UpdateContactsTool::class,
        QueryAudienceCountsTool::class,
    ];

    protected array $resources = [];

    protected array $prompts = [];
}
