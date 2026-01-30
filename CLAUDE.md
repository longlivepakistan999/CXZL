# CLAUDE.md - AI Assistant Guidelines for CXZL

This document provides guidance for AI assistants working with the CXZL repository.

## Repository Overview

- **Repository**: CXZL
- **Owner**: longlivepakistan999
- **Status**: New repository (initial setup)

## Project Structure

```
CXZL/
├── CLAUDE.md          # AI assistant guidelines (this file)
└── (project files to be added)
```

As the project grows, update this section with the actual directory structure.

## Development Workflow

### Branch Strategy

- **Main branch**: Primary stable branch
- **Feature branches**: Use `feature/<name>` for new features
- **Bug fixes**: Use `fix/<name>` for bug fixes
- **Claude branches**: AI-assisted work uses `claude/` prefixed branches

### Commit Guidelines

1. Write clear, descriptive commit messages
2. Use conventional commit format when applicable:
   - `feat:` for new features
   - `fix:` for bug fixes
   - `docs:` for documentation changes
   - `refactor:` for code refactoring
   - `test:` for adding tests
   - `chore:` for maintenance tasks

### Pull Request Process

1. Create a feature branch from main
2. Make changes and commit
3. Push to the remote repository
4. Create a pull request with a clear description
5. Address review feedback

## Code Conventions

### General Principles

- Keep code simple and readable
- Follow the principle of least surprise
- Write self-documenting code with clear naming
- Add comments only when the logic isn't self-evident

### File Organization

- Group related functionality together
- Use descriptive file and directory names
- Keep files focused on a single responsibility

## Commands Reference

### Git Operations

```bash
# Check status
git status

# Create and switch to a new branch
git checkout -b <branch-name>

# Stage changes
git add <file>

# Commit changes
git commit -m "message"

# Push to remote
git push -u origin <branch-name>
```

### (Add project-specific commands as they are established)

## Testing

*(Add testing instructions and commands as the project develops)*

## Building

*(Add build instructions as the project develops)*

## Dependencies

*(List project dependencies as they are added)*

## Configuration

*(Document configuration files and environment variables as needed)*

## AI Assistant Guidelines

### When Working on This Repository

1. **Read before modifying**: Always read existing code before making changes
2. **Keep changes minimal**: Only make changes that are directly requested
3. **Avoid over-engineering**: Don't add features beyond what's asked
4. **Follow existing patterns**: Match the style of surrounding code
5. **Test changes**: Verify changes work before committing

### What to Avoid

- Don't introduce security vulnerabilities
- Don't add unnecessary dependencies
- Don't create files unless absolutely necessary
- Don't add excessive comments or documentation
- Don't make "improvements" that weren't requested

### Common Tasks

When asked to work on this repository, common tasks may include:

1. **Adding new features**: Implement functionality as specified
2. **Fixing bugs**: Identify and resolve issues
3. **Refactoring**: Improve code structure when requested
4. **Documentation**: Update docs when explicitly asked

## Troubleshooting

*(Add common issues and solutions as they are discovered)*

---

*Last updated: 2026-01-30*
*This document should be updated as the project evolves.*
