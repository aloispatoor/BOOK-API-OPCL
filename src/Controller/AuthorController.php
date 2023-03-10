<?php

namespace App\Controller;

use App\Entity\Author;
use App\Repository\AuthorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class AuthorController extends AbstractController
{
    #[Route('/api/authors', name: 'authors', methods: ['GET'])]
    public function getAllAuthor(AuthorRepository $authorRepository, SerializerInterface $serializer, TagAwareCacheInterface $cachePool, Request $request): JsonResponse
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);

        $idCache = "authors-" . $page . "-" . $limit;
        $jsonAuthorList = $cachePool->get($idCache, function(ItemInterface $item) use ($authorRepository, $page, $limit, $serializer){
            $item->tag("bookCache");
            $authorList = $authorRepository->findAllWithPagination($page, $limit);
            return $serializer->serialize($authorList, 'json', ['groups' => 'getAuthors']);
        });
        return new JsonResponse($jsonAuthorList, Response::HTTP_OK, [], true);
    }

    #[Route('/api/authors/{id}', name: 'oneAuthor', methods: ['GET'])]
    public function getOneAuthor(Author $author, SerializerInterface $serializer): JsonResponse
    {
        $jsonAuthor = $serializer->serialize($author, 'json', ['groups' => 'getAuthors']);
        return new JsonResponse($jsonAuthor, Response::HTTP_OK, ['accept' => 'json'], true);
    }

    #[Route('/api/authors/{id}', name: 'deleteAuthor', methods: ['DELETE'])]
    public function deleteAuthor(Author $author, EntityManagerInterface $em, TagAwareCacheInterface $cachePool): JsonResponse
    {
        $cachePool->invalidateTags(["authorsCache"]);
        $em->remove($author);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/authors', name: 'author', methods: ['POST'])]
    public function createAuthor(EntityManagerInterface $em, ValidatorInterface $validator, SerializerInterface $serializer, Request $request, urlGeneratorInterface $urlGenerator): JsonResponse
    {
        $author = $serializer->deserialize($request->getContent(), Author::class, 'json');

        $errors = $validator->validate($author);
        if($errors->count() > 0){
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }

        $em->persist($author);
        $em->flush();

        $jsonBook = $serializer->serialize($author, 'json', ['groups' => 'getAuthors']);

        $location = $urlGenerator->generate('oneAuthor', ['id' => $author->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse($jsonBook, Response::HTTP_CREATED, ["Location" => $location], true);
    }

    #[Route('/api/authors/{id}', name:"updateAuthor", methods:['PUT'])]
    public function updateAuthor(Request $request, SerializerInterface $serializer, ValidatorInterface $validator, Author $currentAuthor, EntityManagerInterface $em): JsonResponse
    {
        $updatedAuthor = $serializer->deserialize($request->getContent(),
            Author::class,
            'json',
            [AbstractNormalizer::OBJECT_TO_POPULATE => $currentAuthor]);

        $errors = $validator->validate($updatedAuthor);
        if($errors->count() > 0){
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }

        $em->persist($updatedAuthor);
        $em->flush();
        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
