# CLAUDE.md - AI Assistant Guidelines for CXZL

## Repository Overview

- **Repository**: CXZL
- **Owner**: longlivepakistan999
- **Status**: Initial setup - awaiting project implementation

## Project Structure

```
CXZL/
└── CLAUDE.md    # AI assistant guidelines (this file)
```

*Update this section as files are added to the project.*

## Development Workflow

### Branch Naming

| Type | Pattern | Example |
|------|---------|---------|
| Features | `feature/<name>` | `feature/user-auth` |
| Bug fixes | `fix/<name>` | `fix/login-error` |
| AI-assisted | `claude/<name>` | `claude/implement-api` |

### Commit Format

Use conventional commits:
- `feat:` new features
- `fix:` bug fixes
- `docs:` documentation
- `refactor:` code restructuring
- `test:` test additions
- `chore:` maintenance

### Pull Requests

1. Branch from main
2. Commit changes
3. Push and create PR with clear description
4. Address review feedback

## Code Conventions

- Keep code simple and readable
- Use clear, descriptive naming
- Group related functionality together
- One responsibility per file
- Comment only when logic isn't self-evident

## AI Assistant Guidelines

### Do

- Read existing code before modifying
- Make only requested changes
- Follow existing patterns and style
- Verify changes work before committing

### Don't

- Introduce security vulnerabilities
- Add unnecessary dependencies
- Create files unless necessary
- Over-engineer or add unrequested features
- Add excessive comments or documentation

## Quick Reference

```bash
git status                           # Check status
git checkout -b <branch>             # New branch
git add <file>                       # Stage changes
git commit -m "type: message"        # Commit
git push -u origin <branch>          # Push
```

---

*Last updated: 2026-01-30*
