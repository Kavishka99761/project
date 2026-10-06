<?php

namespace App\Services\Nlp;

/**
 * English stop-word lists.
 *
 *   COMMON  — function words removed before indexing and similarity scoring.
 *   GENERIC — content-light academic filler that should never be shown to a
 *             student as a "keyword" (lecture, example, section, …) but is
 *             still searchable.
 */
final class StopWords
{
    public const COMMON = [
        'a', 'about', 'above', 'after', 'again', 'against', 'all', 'almost', 'along', 'also', 'although',
        'always', 'am', 'among', 'an', 'and', 'another', 'any', 'anyone', 'anything', 'are', 'around', 'as',
        'at', 'be', 'became', 'because', 'become', 'becomes', 'been', 'before', 'being', 'below', 'between',
        'both', 'but', 'by', 'can', 'cannot', 'could', 'did', 'do', 'does', 'doing', 'done', 'down', 'during',
        'each', 'either', 'else', 'enough', 'etc', 'even', 'ever', 'every', 'few', 'for', 'from', 'further',
        'get', 'gets', 'given', 'gives', 'go', 'goes', 'going', 'got', 'had', 'has', 'have', 'having', 'he',
        'her', 'here', 'hers', 'herself', 'him', 'himself', 'his', 'how', 'however', 'i', 'if', 'in', 'into',
        'is', 'it', 'its', 'itself', 'just', 'least', 'less', 'let', 'like', 'made', 'make', 'makes', 'many',
        'may', 'me', 'might', 'more', 'most', 'much', 'must', 'my', 'myself', 'neither', 'never', 'no', 'nor',
        'not', 'now', 'of', 'off', 'often', 'on', 'once', 'one', 'only', 'onto', 'or', 'other', 'others',
        'otherwise', 'our', 'ours', 'ourselves', 'out', 'over', 'own', 'per', 'perhaps', 'quite', 'rather',
        'really', 's', 'same', 'say', 'says', 'see', 'seen', 'several', 'shall', 'she', 'should', 'since',
        'so', 'some', 'something', 'still', 'such', 't', 'than', 'that', 'the', 'their', 'theirs', 'them',
        'themselves', 'then', 'there', 'thereby', 'therefore', 'these', 'they', 'this', 'those', 'though',
        'through', 'thus', 'to', 'together', 'too', 'toward', 'towards', 'under', 'until', 'up', 'upon', 'us',
        'very', 'via', 'was', 'we', 'well', 'were', 'what', 'whatever', 'when', 'whenever', 'where', 'whereas',
        'wherever', 'whether', 'which', 'while', 'who', 'whoever', 'whom', 'whose', 'why', 'will', 'with',
        'within', 'without', 'would', 'yet', 'you', 'your', 'yours', 'yourself', 'yourselves', 'ie', 'eg',
        'i.e', 'e.g', 'll', 've', 're', 'd', 'm', 'don', 'doesn', 'didn', 'isn', 'aren', 'wasn', 'weren',
        'won', 'wouldn', 'shouldn', 'couldn', 'hasn', 'haven', 'hadn',
    ];

    public const GENERIC = [
        'use', 'used', 'uses', 'using', 'example', 'examples', 'lecture', 'lectures', 'slide', 'slides',
        'chapter', 'section', 'sections', 'page', 'pages', 'figure', 'figures', 'table', 'tables', 'note',
        'notes', 'following', 'follows', 'include', 'includes', 'including', 'included', 'called', 'known',
        'different', 'various', 'important', 'first', 'second', 'third', 'new', 'way', 'ways', 'type',
        'types', 'number', 'numbers', 'part', 'parts', 'thing', 'things', 'case', 'cases', 'kind', 'kinds',
        'lot', 'lots', 'able', 'need', 'needs', 'needed', 'want', 'like', 'look', 'take', 'takes', 'taken',
        'put', 'set', 'sets', 'two', 'three', 'four', 'five', 'week', 'weeks', 'today', 'time', 'times',
        'good', 'better', 'best', 'main', 'mainly', 'simply', 'simple', 'usually', 'generally', 'typically',
        'specific', 'specifically', 'particular', 'particularly', 'certain', 'common', 'commonly', 'whole',
        'student', 'students', 'module', 'unit', 'course', 'term', 'terms', 'mean', 'means', 'meaning',
        'shown', 'show', 'shows', 'describe', 'describes', 'described', 'discuss', 'discussed', 'consider',
        'considered', 'provide', 'provides', 'provided', 'allow', 'allows', 'allowed', 'help', 'helps',
        'based', 'within', 'without', 'able', 'order', 'result', 'results', 'given', 'instead', 'already',
    ];

    /** @var array<string, true>|null */
    private static ?array $common = null;

    /** @var array<string, true>|null */
    private static ?array $generic = null;

    public static function isCommon(string $word): bool
    {
        self::$common ??= array_fill_keys(self::COMMON, true);

        return isset(self::$common[$word]);
    }

    public static function isGeneric(string $word): bool
    {
        self::$generic ??= array_fill_keys(self::GENERIC, true);

        return isset(self::$generic[$word]) || self::isCommon($word);
    }
}
