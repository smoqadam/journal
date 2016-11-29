<?php

namespace eLife\Journal\ViewModel\Converter\Reference;

use eLife\ApiSdk\Model\Reference\BookReference;
use eLife\Journal\ViewModel\Converter\ViewModelConverter;
use eLife\Patterns\ViewModel;

final class BookReferenceConverter implements ViewModelConverter
{
    use HasAuthors;
    use HasPublisher;

    /**
     * @param BookReference $object
     */
    public function convert($object, string $viewModel = null, array $context = []) : ViewModel
    {
        $title = $object->getBookTitle();
        if ($object->getVolume()) {
            $title .= ', '.$object->getVolume();
        }
        if ($object->getEdition()) {
            $title .= ' ('.$object->getEdition().')';
        }

        $origin = [$this->publisherToString($object->getPublisher())];
        if ($object->getPmid()) {
            $origin[] = 'PMID '.$object->getPmid();
        }
        if ($object->getIsbn()) {
            $origin[] = 'ISBN '.$object->getIsbn();
        }

        $authors = [$this->createAuthors($object->getAuthors(), $object->authorsEtAl(), [$object->getDate()->format().$object->getDiscriminator()])];

        if ($object->getDoi()) {
            return ViewModel\Reference::withDoi($title, new ViewModel\Doi($object->getDoi()), $origin, $authors);
        }

        return ViewModel\Reference::withOutDoi(new ViewModel\Link($title), $origin, $authors);
    }

    public function supports($object, string $viewModel = null, array $context = []) : bool
    {
        return $object instanceof BookReference;
    }
}