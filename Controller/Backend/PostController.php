<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski <pjedrzejewski@diweb.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sylius\Bundle\BloggerBundle\Controller\Backend;

use Sylius\Bundle\BloggerBundle\EventDispatcher\Event\FilterPostEvent;
use Sylius\Bundle\BloggerBundle\EventDispatcher\SyliusBloggerEvents;
use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Post backend controller.
 *
 * @author Paweł Jędrzejewski <pjedrzejewski@diweb.pl>
 */
class PostController extends ContainerAware
{
    /**
     * Display table of posts.
     *
     * @param Request $request
     *
     * @return Response
     */
    public function listAction(Request $request)
    {
        $postManager = $this->container->get('sylius_blogger.manager.post');
        $postSorter = $this->container->get('sylius_blogger.sorter.post');

        $paginator = $postManager->createPaginator($postSorter);
        $paginator->setCurrentPage($request->query->get('page', 1), true, true);

        $posts = $paginator->getCurrentPageResults();

        return $this->container->get('templating')->renderResponse('SyliusBloggerBundle:Backend/Post:list.html.'.$this->getEngine(), array(
            'posts'  => $posts,
            'paginator' => $paginator,
            'sorter'    => $postSorter
        ));
    }

    /**
     * Shows a post.
     *
     * @param mixed $id The post id
     *
     * @return Response
     */
    public function showAction($id)
    {
        $post = $this->findPostOr404($id);

        return $this->container->get('templating')->renderResponse('SyliusBloggerBundle:Backend/Post:show.html.'.$this->getEngine(), array(
            'post' => $post
        ));
    }

    /**
     * Creating a new post.
     *
     * @param Request $request
     *
     * @return Response
     */
    public function createAction(Request $request)
    {
        $post = $this->container->get('sylius_blogger.manager.post')->createPost();
        $form = $this->container->get('form.factory')->create('sylius_blogger_post', $post);

        if ('POST' === $request->getMethod()) {
            $form->bindRequest($request);

            if ($form->isValid()) {
                $this->container->get('event_dispatcher')->dispatch(SyliusBloggerEvents::POST_CREATE, new FilterPostEvent($post));
                $this->container->get('sylius_blogger.manipulator.post')->create($post);

                return new RedirectResponse($this->container->get('router')->generate('sylius_blogger_backend_post_list'));
            }
        }

        return $this->container->get('templating')->renderResponse('SyliusBloggerBundle:Backend/Post:create.html.'.$this->getEngine(), array(
            'form' => $form->createView()
        ));
    }

    /**
     * Updating a post.
     *
     * @param Request $request
     * @param mixed   $id      The post id
     *
     * @return Response
     */
    public function updateAction(Request $request, $id)
    {
        $post = $this->findPostOr404($id);
        $form = $this->container->get('form.factory')->create('sylius_blogger_post', $post);

        if ('POST' === $request->getMethod()) {
            $form->bindRequest($request);

            if ($form->isValid()) {
                $this->container->get('event_dispatcher')->dispatch(SyliusBloggerEvents::POST_UPDATE, new FilterPostEvent($post));
                $this->container->get('sylius_blogger.manipulator.post')->update($post);

                return new RedirectResponse($this->container->get('router')->generate('sylius_blogger_backend_post_show', array(
                    'id' => $post->getId()
                )));
            }
        }

        return $this->container->get('templating')->renderResponse('SyliusBloggerBundle:Backend/Post:update.html.'.$this->getEngine(), array(
            'form' => $form->createView(),
            'post' => $post
        ));
    }

    /**
     * Deletes post.
     *
     * @param mixed $id The post id
     *
     * @return Response
     */
    public function deleteAction($id)
    {
        $post = $this->findPostOr404($id);

        $this->container->get('event_dispatcher')->dispatch(SyliusBloggerEvents::POST_DELETE, new FilterPostEvent($post));
        $this->container->get('sylius_blogger.manipulator.post')->delete($post);

        return new RedirectResponse($this->container->get('router')->generate('sylius_blogger_backend_post_list'));
    }

    /**
     * Publishes post.
     *
     * @param mixed $id The post id
     *
     * @return Response
     */
    public function publishAction($id)
    {
        $post = $this->findPostOr404($id);

        if (!$post->isPublished()) {
            $this->container->get('event_dispatcher')->dispatch(SyliusBloggerEvents::POST_PUBLISH, new FilterPostEvent($post));
            $this->container->get('sylius_blogger.manipulator.post')->publish($post);
        }

        return new RedirectResponse($this->container->get('router')->generate('sylius_blogger_backend_post_list'));
    }

    /**
     * Unpublishes post.
     *
     * @param mixed $id The post id
     *
     * @return Response
     */
    public function unpublishAction($id)
    {
        $post = $this->findPostOr404($id);

        if ($post->isPublished()) {
            $this->container->get('event_dispatcher')->dispatch(SyliusBloggerEvents::POST_UNPUBLISH, new FilterPostEvent($post));
            $this->container->get('sylius_blogger.manipulator.post')->unpublish($post);
        }

        return new RedirectResponse($this->container->get('router')->generate('sylius_blogger_backend_post_list'));
    }

    /**
     * Tries to find post by id.
     * Throws 404 exception when not found.
     *
     * @param mixed $id
     *
     * @return PostInterface
     *
     * @throws NotFoundHttpException
     */
    protected function findPostOr404($id)
    {
        if (!$post = $this->container->get('sylius_blogger.manager.post')->findPost($id)) {
            throw new NotFoundHttpException('Requested post does not exist');
        }

        return $post;
    }

    /**
     * Returns templating engine name.
     *
     * @return string
     */
    protected function getEngine()
    {
        return $this->container->getParameter('sylius_blogger.engine');
    }
}
